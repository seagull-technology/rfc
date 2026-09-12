#!/usr/bin/env python3
"""Bounded anonymous HTTP checks for the authorized Filmjordan test environment.

Uses fresh in-memory cookie jars, synthetic unknown identifiers and random wrong
passwords. Never imports browser cookies, submits a valid password, performs a
government lookup, or requests recovery for a supplied/known account. Unknown
recovery requests create no-op queue jobs according to the reviewed application
source. Run only against the authorized test environment.

Default invocation prints the plan. --run performs at most 13 anonymous POSTs.
--compare-fixture additionally prompts without echo for one controlled fixture
identifier and performs two wrong-password comparisons; no valid password is
accepted. This optional small sample cannot establish timing indistinguishability.
Evidence excludes cookie values, CSRF tokens, identifiers, passwords and bodies.
"""

import argparse
import datetime
import getpass
import hashlib
import html
from html.parser import HTMLParser
import http.cookiejar
import json
from pathlib import Path
import re
import secrets
import ssl
import statistics
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

ORIGIN = "https://filmjordan.jo"
MESSAGES = {
    "en": {
        "invalid_login": "The provided login credentials are invalid.",
        "neutral_reset": "If the identifier matches an account, a password reset code has been sent to its registered mobile number.",
        "neutral_otp": "If the identifier matches an account, enter the 5-digit code sent to its registered mobile number.",
        "invalid_otp": "The verification code is invalid or expired.",
        "resend_wait": "You can request a new code in",
        "debug_code": "Development OTP:",
    },
    "ar": {
        "invalid_login": "بيانات الدخول غير صحيحة.",
        "neutral_reset": "إذا كانت البيانات مطابقة لحساب مسجل، فسيتم إرسال رمز إعادة تعيين كلمة المرور إلى رقم الهاتف المسجل.",
        "neutral_otp": "إذا كانت البيانات مطابقة لحساب مسجل، فأدخل رمز التحقق المكوّن من 5 أرقام والمرسل إلى رقم الهاتف المسجل.",
        "invalid_otp": "رمز التحقق غير صحيح أو منتهي الصلاحية.",
        "resend_wait": "يمكنك طلب رمز جديد بعد",
        "debug_code": "رمز التطوير:",
    },
}


def utc():
    return datetime.datetime.now(datetime.timezone.utc).isoformat()


def normalize_space(value):
    return " ".join(value.split())


class FormParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.csrf = None
        self.nonces = []
        self.text = []
        self.hidden_depth = 0

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag in ("script", "style"):
            self.hidden_depth += 1
        if attrs.get("nonce"):
            self.nonces.append(attrs["nonce"])
        if tag == "input" and attrs.get("name") == "_token":
            self.csrf = attrs.get("value")
        if tag == "meta" and attrs.get("name") == "csrf-token":
            self.csrf = attrs.get("content")

    def handle_endtag(self, tag):
        if tag in ("script", "style"):
            self.hidden_depth = max(0, self.hidden_depth - 1)

    def handle_data(self, data):
        if not self.hidden_depth:
            self.text.append(data)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def safe_path(url):
    parsed = urllib.parse.urlsplit(urllib.parse.urljoin(ORIGIN + "/", url))
    if (parsed.scheme != "https" or parsed.hostname != "filmjordan.jo"
            or parsed.port not in (None, 443) or parsed.username or parsed.password
            or parsed.query or parsed.fragment):
        raise RuntimeError("Request/redirect left the approved HTTPS origin or included unexpected URL data.")
    if not re.fullmatch(r"/(ar|en)/(sign-in|forgot-password(?:/verify-otp(?:/resend)?)?)", parsed.path):
        raise RuntimeError("Request/redirect left the bounded anonymous endpoint list.")
    return parsed.path


def cookie_attributes(headers):
    result = []
    for value in headers.get_all("Set-Cookie", []):
        parts = value.split(";")
        cookie_name = parts[0].split("=", 1)[0].strip()
        item = {"name": cookie_name, "secure": False, "http_only": False,
                "same_site": None, "domain": None, "path": None}
        for part in parts[1:]:
            key, _, attribute = part.strip().partition("=")
            key = key.lower()
            if key == "secure":
                item["secure"] = True
            elif key == "httponly":
                item["http_only"] = True
            elif key in ("samesite", "domain", "path"):
                item[{"samesite": "same_site"}.get(key, key)] = attribute
        result.append(item)
    return result


class Review:
    def __init__(self, output, interval, fixture_identifier=None):
        self.output = output
        self.interval = interval
        self.fixture_identifier = fixture_identifier
        self.wrong_password = "Invalid-" + secrets.token_urlsafe(32)
        self.prefix = "SECURITY-REVIEW-" + secrets.token_hex(16)
        self.sensitive = [self.wrong_password, self.prefix]
        if fixture_identifier:
            self.sensitive.append(fixture_identifier)
        self.last_request = 0.0
        self.request_count = 0
        self.post_count = 0
        self.report = {
            "target": ORIGIN, "started_utc": utc(), "mode": "anonymous-synthetic",
            "scope": "Fresh anonymous sessions; wrong passwords and synthetic unknown recovery only.",
            "requests": [], "checks": [],
            "limits": [
                "No browser cookies, valid credentials or recovery for known accounts were used.",
                "Unknown recovery may enqueue user_id=0 no-op jobs; SMS absence is inferred from reviewed code, not provider logs.",
                "Anonymous auth throttles do not close V04 authenticated contact/configuration write findings.",
                "No node pinning, shared-store inspection, hourly-limit exhaustion or genuinely concurrent replay was performed.",
                "These small samples do not establish response-time distribution equivalence or close V06.",
                "Active/pending/inactive/archived account and registration/identifier-type comparisons require controlled fixtures.",
            ],
        }

    def save(self):
        self.output.parent.mkdir(parents=True, exist_ok=True)
        self.output.write_text(json.dumps(self.report, ensure_ascii=False, indent=2) + "\n")
        self.output.chmod(0o600)

    def check(self, name, passed, detail):
        self.report["checks"].append({"name": name, "passed": bool(passed), "detail": detail})
        self.save()
        print(("PASS " if passed else "FAIL ") + name, flush=True)
        if not passed:
            raise RuntimeError("A bounded assertion failed; stopped without additional requests: " + name)

    def session(self):
        return urllib.request.build_opener(
            urllib.request.ProxyHandler({}),
            urllib.request.HTTPSHandler(context=ssl.create_default_context()),
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
            NoRedirect(),
        )

    def request(self, session, path, case, data=None):
        path = safe_path(path)
        if self.request_count >= 60 or (data is not None and self.post_count >= 15):
            raise RuntimeError("The hard request bound was reached.")
        time.sleep(max(0, self.interval - (time.monotonic() - self.last_request)))
        headers = {"User-Agent": "SecurityReview/1.0", "Accept": "text/html", "Accept-Language": path.split("/")[1]}
        encoded = None
        if data is not None:
            headers["Content-Type"] = "application/x-www-form-urlencoded"
            headers["Origin"] = ORIGIN
            encoded = urllib.parse.urlencode(data).encode()
            self.post_count += 1
        self.request_count += 1
        start = time.monotonic()
        request = urllib.request.Request(ORIGIN + path, data=encoded, headers=headers)
        try:
            response = session.open(request, timeout=20)
        except urllib.error.HTTPError as response_error:
            response = response_error
        except urllib.error.URLError:
            raise RuntimeError("The approved endpoint could not be reached; no automatic retry was attempted.") from None
        with response:
            raw = response.read(2_000_001)
            if len(raw) > 2_000_000:
                raise RuntimeError("Response exceeded the bounded body size.")
            body = raw.decode("utf-8", errors="replace")
            elapsed = round((time.monotonic() - start) * 1000, 2)
            self.last_request = time.monotonic()
            parser = FormParser()
            parser.feed(body)
            if parser.csrf:
                self.sensitive.append(parser.csrf)
            self.sensitive.extend(parser.nonces)
            normalized = body
            for secret in sorted(set(self.sensitive), key=len, reverse=True):
                normalized = normalized.replace(secret, "<redacted>")
            normalized = re.sub(r"nonce=[\"'][^\"']+[\"']", 'nonce="<redacted>"', normalized)
            normalized = re.sub(r"\b\d{2}:\d{2}\b", "<countdown>", normalized)
            normalized = normalize_space(normalized)
            language = path.split("/")[1]
            page_text = normalize_space(html.unescape(" ".join(parser.text)))
            signals = {name: normalize_space(message) in page_text for name, message in MESSAGES[language].items()}
            location = response.headers.get("Location")
            record = {
                "case": case, "method": request.get_method(), "path": path,
                "status": response.status, "elapsed_ms": elapsed,
                "location_path": safe_path(location) if location else None,
                "content_type": response.headers.get("Content-Type"),
                "retry_after": response.headers.get("Retry-After"),
                "rate_limit": response.headers.get("X-RateLimit-Limit"),
                "rate_remaining": response.headers.get("X-RateLimit-Remaining"),
                "cookie_attributes": cookie_attributes(response.headers),
                "headers": {key: response.headers.get(key) for key in [
                    "Strict-Transport-Security", "X-Content-Type-Options", "X-Frame-Options",
                    "Referrer-Policy", "Cache-Control"]},
                "body_bytes": len(raw), "normalized_body_sha256": hashlib.sha256(normalized.encode()).hexdigest(),
                "signals": signals, "csrf_present": bool(parser.csrf),
            }
            self.report["requests"].append(record)
            self.save()
            return record, parser.csrf

    def get_form(self, session, path, case):
        record, token = self.request(session, path, case)
        self.check(case + "-form", record["status"] == 200 and bool(token), "HTTP 200 and a fresh form CSRF token are required before POST.")
        return token

    def invalid_login(self, session, language, identifier, case, expected_status=302):
        path = "/" + language + "/sign-in"
        token = self.get_form(session, path, case + "-get")
        record, _ = self.request(session, path, case, {"_token": token, "identifier": identifier, "password": self.wrong_password})
        self.check(case + "-status", record["status"] == expected_status, "Expected anonymous wrong-password/limiter response.")
        if expected_status == 302:
            self.check(case + "-redirect", record["location_path"] == path, "Invalid credentials must return to sign-in, never OTP or an authenticated page.")
            page, _ = self.request(session, path, case + "-message")
            self.check(case + "-message", page["status"] == 200 and page["signals"]["invalid_login"], "The localized neutral invalid-credentials message must render.")
        else:
            retry = record["retry_after"]
            self.check(case + "-retry-after", bool(retry) and retry.isdigit() and int(retry) > 0, "HTTP 429 must include a positive Retry-After.")

    def recovery(self, language):
        session = self.session()
        path = "/" + language + "/forgot-password"
        otp_path = path + "/verify-otp"
        identifier = self.prefix + "-RESET-" + language.upper()
        self.sensitive.append(identifier)
        token = self.get_form(session, path, language + "-recovery-get")
        record, _ = self.request(session, path, language + "-unknown-recovery", {"_token": token, "identifier": identifier})
        self.check(language + "-unknown-recovery-envelope", record["status"] == 302 and record["location_path"] == otp_path, "Unknown synthetic identifiers must receive the neutral OTP-page redirect.")
        page, token = self.request(session, otp_path, language + "-unknown-otp-page")
        self.check(language + "-unknown-otp-page", page["status"] == 200 and token and page["signals"]["neutral_reset"] and page["signals"]["neutral_otp"] and not page["signals"]["debug_code"], "Neutral acknowledgement/introduction must render without any development OTP.")
        invalid, _ = self.request(session, otp_path, language + "-unknown-invalid-otp", {"_token": token, "code": "00000"})
        self.check(language + "-invalid-otp-redirect", invalid["status"] == 302 and invalid["location_path"] == otp_path, "An unknown session must not accept an OTP or issue a password-reset token.")
        page, token = self.request(session, otp_path, language + "-invalid-otp-message")
        self.check(language + "-invalid-otp-message", page["status"] == 200 and token and page["signals"]["invalid_otp"], "Invalid OTP message must be localized and neutral.")
        resend, _ = self.request(session, otp_path + "/resend", language + "-unknown-immediate-resend", {"_token": token})
        self.check(language + "-resend-redirect", resend["status"] == 302 and resend["location_path"] == otp_path, "Immediate resend must remain on the OTP page.")
        page, _ = self.request(session, otp_path, language + "-resend-message")
        self.check(language + "-resend-cooldown", page["status"] == 200 and page["signals"]["resend_wait"], "A session-based cooldown must apply to the unknown identifier too.")

    def run(self):
        self.save()
        sessions = {language: self.session() for language in ("ar", "en")}
        identifier = self.prefix + "-LOGIN"
        self.sensitive.append(identifier)
        for attempt, language in enumerate(["ar", "en", "ar", "en", "ar", "en"], 1):
            self.invalid_login(sessions[language], language, identifier, "unknown-login-" + str(attempt), 302 if attempt <= 5 else 429)
        self.invalid_login(self.session(), "ar", identifier, "unknown-login-new-session", 429)
        if self.fixture_identifier:
            self.report["mode"] = "anonymous-plus-controlled-fixture-wrong-password"
            for language in ("ar", "en"):
                self.invalid_login(self.session(), language, self.fixture_identifier, language + "-fixture-wrong-password")
        for language in ("ar", "en"):
            self.recovery(language)
        timing = {}
        for case in ("unknown-login", "unknown-recovery", "unknown-invalid-otp"):
            samples = [row["elapsed_ms"] for row in self.report["requests"] if row["method"] == "POST" and case in row["case"] and row["status"] == 302]
            timing[case] = {"samples": len(samples), "median_ms": statistics.median(samples) if samples else None, "observations_ms": samples}
        self.report.update({"finished_utc": utc(), "request_count": self.request_count, "post_count": self.post_count,
                            "sample_timings": timing, "bounded_checks_passed": True, "V04_closed": False, "V06_closed": False})
        self.save()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--run", action="store_true", help="Execute the explicitly authorized bounded test-environment checks.")
    parser.add_argument("--output", type=Path, default=Path("/private/tmp/rfc-security-verification-" + datetime.datetime.now().strftime("%Y%m%d") + "/anonymous-http-results.json"))
    parser.add_argument("--compare-fixture", action="store_true", help="Prompt privately for one controlled fixture identifier; wrong-password login only.")
    args = parser.parse_args()
    if not args.run:
        print("Plan: 7 wrong-password synthetic login POSTs across ar/en and fresh sessions; 2 synthetic unknown recovery, 2 invalid OTP, and 2 immediate resend POSTs. Fresh in-memory cookies/CSRF; no redirects outside the approved endpoint list. Optional fixture mode adds 2 wrong-password POSTs. Add --run to execute.")
        return 0
    fixture = None
    if args.compare_fixture:
        if not sys.stdin.isatty():
            parser.error("Fixture input requires an interactive terminal; never pass identifiers or credentials as arguments.")
        fixture = getpass.getpass("Controlled test-fixture identifier (hidden; wrong-password comparisons only): ").strip()
        if not fixture or len(fixture) > 255:
            parser.error("A nonempty controlled fixture identifier up to 255 characters is required.")
    review = Review(args.output, interval=0.5, fixture_identifier=fixture)
    try:
        review.run()
    except Exception as error:
        review.report.update({"finished_utc": utc(), "request_count": review.request_count, "post_count": review.post_count,
                              "bounded_checks_passed": False, "stopped_reason": str(error), "V04_closed": False, "V06_closed": False})
        review.save()
        print("Stopped; sanitized evidence saved at " + str(args.output), file=sys.stderr)
        return 1
    print("Completed bounded checks. Evidence: " + str(args.output))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
