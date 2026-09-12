#!/usr/bin/env python3
"""Create one authorized, disposable NGO/school registration on filmjordan.jo.

Uses its own anonymous session and no real contact details. The reviewed app
normalizes 0000000000 to an empty phone and sends no registration notification.
An acknowledgement is deliberately generic and does NOT prove record creation;
an administrator must locate the exact generated name. Duplicate empty phones
can prevent creation. Do not automatically retry or substitute dialable numbers.
The generated password is written only to an exclusive private local file.
"""

import argparse
import datetime
import hashlib
from html.parser import HTMLParser
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import ssl
import urllib.error
import urllib.parse
import urllib.request

ORIGIN = "https://filmjordan.jo"


class RegistrationForm(HTMLParser):
    def __init__(self, kind):
        super().__init__(convert_charrefs=True)
        self.kind = kind
        self.inside = False
        self.found = False
        self.token = None
        self.action = None
        self.required_checkboxes = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "form":
            self.inside = attrs.get("data-register-form") == self.kind
            if self.inside:
                self.found = True
                self.action = attrs.get("action")
        if self.inside and tag == "input":
            if attrs.get("name") == "_token":
                self.token = attrs.get("value")
            if attrs.get("type") == "checkbox":
                self.required_checkboxes.append(attrs.get("name", "unnamed"))

    def handle_endtag(self, tag):
        if tag == "form":
            self.inside = False


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def safe_path(url):
    parsed = urllib.parse.urlsplit(urllib.parse.urljoin(ORIGIN, url))
    if (parsed.scheme != "https" or parsed.hostname != "filmjordan.jo"
            or parsed.port not in (None, 443) or parsed.username or parsed.password
            or parsed.query or parsed.fragment
            or parsed.path not in ("/en/register", "/en/register/submitted")):
        raise RuntimeError("Unexpected registration URL; no request will follow it.")
    return parsed.path


def fixture_pdf():
    text = b"BT /F1 14 Tf 50 760 Td (SECURITY VERIFICATION FIXTURE - NOT AN OFFICIAL DOCUMENT) Tj ET"
    objects = [
        b"<< /Type /Catalog /Pages 2 0 R >>",
        b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
        b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        b"<< /Length " + str(len(text)).encode() + b" >>\nstream\n" + text + b"\nendstream",
    ]
    result = bytearray(b"%PDF-1.4\n")
    offsets = [0]
    for index, obj in enumerate(objects, 1):
        offsets.append(len(result))
        result.extend(str(index).encode() + b" 0 obj\n" + obj + b"\nendobj\n")
    start = len(result)
    result.extend(b"xref\n0 6\n0000000000 65535 f \n")
    for offset in offsets[1:]:
        result.extend(f"{offset:010d} 00000 n \n".encode())
    result.extend(b"trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" + str(start).encode() + b"\n%%EOF\n")
    return bytes(result)


def private_json(path, value):
    with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), "w") as handle:
        json.dump(value, handle, indent=2)
        handle.write("\n")


def multipart(fields, document):
    boundary = "RegistrationBoundary" + secrets.token_hex(20)
    parts = []
    for name, value in fields.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="registration_document"; filename="verification-fixture.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode() + document + b"\r\n")
    parts.append(f"--{boundary}--\r\n".encode())
    return b"".join(parts), "multipart/form-data; boundary=" + boundary


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--type", choices=("ngo", "school"), required=True)
    parser.add_argument("--run", action="store_true")
    parser.add_argument("--output-directory", type=Path, default=Path("/private/tmp/rfc-security-verification-" + datetime.date.today().strftime("%Y%m%d")))
    args = parser.parse_args()
    if not args.run:
        print("Plan: fresh GET /en/register, one multipart POST for a disposable " + args.type + ", and GET /en/register/submitted only if acknowledged. No lookup, login, recovery, real contacts or terms checkbox submission.")
        return 0
    marker = "SV-" + datetime.date.today().strftime("%Y%m%d") + "-" + secrets.token_hex(8)
    directory = args.output_directory / (marker.lower() + "-" + args.type)
    directory.mkdir(parents=True, mode=0o700, exist_ok=False)
    password = "Aa1!" + secrets.token_urlsafe(36)
    registration = marker + "-" + args.type.upper()
    name = "Security Verification " + args.type.upper() + " " + marker
    fields = {"registration_type": args.type, "entity_name": name,
              "registration_number": registration, "email": marker.lower() + "-" + args.type + "@verification.invalid",
              "phone": "0000000000", "address": "Disposable security verification fixture",
              "description": "Authorized test-environment security verification. Disposable fixture; not a real organization.",
              "password": password, "password_confirmation": password}
    private_json(directory / "credentials.private.json", {"target": ORIGIN, "name": name, "registration_number": registration,
                 "expected_username": args.type + "-" + registration.lower(), "email": fields["email"], "password": password})
    document = fixture_pdf()
    (directory / "verification-fixture.pdf").write_bytes(document)
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}),
        urllib.request.HTTPSHandler(context=ssl.create_default_context()),
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
    report = {"target": ORIGIN, "fixture_name": name, "registration_type": args.type, "registration_number": registration,
              "created_at_utc": datetime.datetime.now(datetime.timezone.utc).isoformat(), "requests": [],
              "creation_verified": False, "limitation": "Generic acknowledgement does not establish creation. Exact new record must be verified by administrator; no automatic retry.",
              "pdf_sha256": hashlib.sha256(document).hexdigest()}

    def request(path, data=None, content_type=None):
        headers = {"User-Agent": "SecurityReview/1.0", "Accept": "text/html"}
        if data is not None:
            headers.update({"Origin": ORIGIN, "Content-Type": content_type})
        req = urllib.request.Request(ORIGIN + safe_path(path), data=data, headers=headers)
        try:
            response = opener.open(req, timeout=30)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read(2_000_001)
            if len(body) > 2_000_000:
                raise RuntimeError("Response exceeded limit.")
            location = response.headers.get("Location")
            row = {"method": req.get_method(), "path": path, "status": response.status,
                   "location_path": safe_path(location) if location else None}
            report["requests"].append(row)
            return row, body.decode("utf-8", errors="replace")

    try:
        row, body = request("/en/register")
        form = RegistrationForm(args.type)
        form.feed(body)
        if row["status"] != 200 or not form.found or not form.token or safe_path(form.action) != "/en/register":
            raise RuntimeError("Expected registration form/CSRF missing; no POST sent.")
        if form.required_checkboxes:
            raise RuntimeError("Registration form contains a checkbox requiring review; no POST sent.")
        fields["_token"] = form.token
        data, content_type = multipart(fields, document)
        row, _ = request("/en/register", data, content_type)
        report["acknowledged"] = row["status"] == 302 and row["location_path"] == "/en/register/submitted"
        if report["acknowledged"]:
            row, _ = request("/en/register/submitted")
            report["acknowledgement_page_status"] = row["status"]
    except Exception as error:
        report["stopped_exception_type"] = type(error).__name__
        report["stopped"] = True
    private_json(directory / "creation-result.json", report)
    print(json.dumps({"fixture_name": name, "registration_number": registration, "acknowledged": report.get("acknowledged", False),
                      "creation_verified": False, "evidence_directory": str(directory)}, indent=2))
    return 0 if report.get("acknowledged") else 1


if __name__ == "__main__":
    raise SystemExit(main())
