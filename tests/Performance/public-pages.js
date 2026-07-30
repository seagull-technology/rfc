import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = (__ENV.BASE_URL || 'http://rfc.test').replace(/\/$/, '');
const locale = __ENV.LOCALE || 'ar';
const paths = (__ENV.PATHS || `/${locale}/sign-in,/${locale}/register,/${locale}/permit-verification`)
    .split(',')
    .map((path) => path.trim())
    .filter(Boolean);
const thinkTime = Number(__ENV.THINK_TIME || 1);

export const options = {
    scenarios: {
        public_pages: {
            executor: 'constant-vus',
            vus: Number(__ENV.VUS || 10),
            duration: __ENV.DURATION || '30s',
        },
    },
    thresholds: {
        checks: ['rate>0.99'],
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<2000', 'p(99)<4000'],
    },
};

export default function () {
    const path = paths[(__VU + __ITER) % paths.length];
    const response = http.get(`${baseUrl}${path}`, {
        tags: { workflow: 'public', path },
    });

    check(response, {
        'public page returns 200': (result) => result.status === 200,
        'public page is not an error document': (result) => !/\b(?:404|419|500|503)\b/.test(result.html('title').text()),
    });

    if (thinkTime > 0) {
        sleep(thinkTime);
    }
}
