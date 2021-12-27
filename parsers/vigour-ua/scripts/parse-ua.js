const initStart = process.hrtime();
const ua = require('vigour-ua');
// Trigger a parse to force cache loading
ua('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(
    require.resolve('vigour-ua')
) + '/package.json');
const version = packageInfo.version;

let hasUa = false;
const uaPos = process.argv.indexOf('--ua');
let line = '';
if (uaPos >= 0) {
    line = process.argv[3];
    hasUa = true;
}

const output = {
    result: null,
    parse_time: 0,
    init_time: initTime,
    memory_used: 0,
    version: version
};

if (hasUa) {
    const start = process.hrtime();
    try {
        const r = ua(line);
    } catch (err) {
        output.result = {
            useragent: line,
            parsed: {
                client: {
                    name: null,
                    version: null,
                    isBot: null,
                    type: null
                },
                platform: {
                    name: null,
                    version: null
                },
                device: {
                    name: null,
                    brand: null,
                    type: null,
                    ismobile: null,
                    istouch: null
                },
                engine: {
                    name: null,
                    version: null
                },
                raw: null
            },
            error: err,
            time: null
        };
    }
    const end = process.hrtime(start)[1] / 1000000000;

    if (typeof r !== 'undefined') {
        output.result = {
            useragent: line,
            parsed: {
                client: {
                    name: r.browser ? r.browser : null,
                    version: r.version ? r.version : null,
                    isBot: null,
                    type: null
                },
                platform: {
                    name: r.platform,
                    version: null
                },
                device: {
                    name: null,
                    brand: null,
                    type: r.device,
                    ismobile:
                        r.device === 'phone' ||
                        r.device === 'mobile' ||
                        r.device === 'tablet' ||
                        r.device === 'wearable'
                            ? true
                            : null,
                    istouch: null
                },
                engine: {
                    name: null,
                    version: null
                },
                raw: r
            },
            time: end
        };
    }
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
