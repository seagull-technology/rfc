import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = (__ENV.BASE_URL || 'http://rfc.test').replace(/\/$/, '');
const locale = __ENV.LOCALE || 'ar';
const paths = (__ENV.PATHS || `/${locale}/control-panel`)
    .split(',')
    .map((path) => path.trim())
    .filter(Boolean);
const cookieName = __ENV.SESSION_COOKIE_NAME || 'rfc-session';
const cookieValue = __ENV.SESSION_COOKIE_VALUE || '';
const thinkTime = Number(__ENV.THINK_TIME || 1);

if (!cookieValue) {
    throw new Error('SESSION_COOKIE_VALUE is required for authenticated performance tests.');
}

export const options = {
    scenarios: {
        authenticated_pages: {
            executor: 'constant-vus',
            vus: Number(__ENV.VUS || 10),
            duration: __ENV.DURATION || '30s',
        },
    },
    thresholds: {
        checks: ['rate>0.99'],
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<3000', 'p(99)<5000'],
    },
};

export default function () {
    const path = paths[(__VU + __ITER) % paths.length];
    const response = http.get(`${baseUrl}${path}`, {
        cookies: {
            [cookieName]: cookieValue,
        },
        redirects: 0,
        tags: { workflow: 'authenticated', path },
    });

    check(response, {
        'authenticated page returns 200': (result) => result.status === 200,
        'authenticated page does not redirect to sign-in': (result) => (
            result.status !== 301
            && result.status !== 302
            && !String(result.headers.Location || '').includes('/sign-in')
        ),
    });

    if (thinkTime > 0) {
        sleep(thinkTime);
    }
}
