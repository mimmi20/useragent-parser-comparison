const initStart = process.hrtime();
const { parseUserAgent } = require('detect-browser');
// Trigger a parse to force cache loading
parseUserAgent('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(
    require.resolve('detect-browser')
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
    const r = parseUserAgent(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result = {
        useragent: line,
        parsed: {
            client: {
                name: r !== null && r.name ? r.name : null,
                version: r !== null && r.version ? r.version : null,
                isBot: null,
                type: null
            },
            platform: {
                name: r !== null && r.os ? r.os : null,
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
            raw: r
        },
        time: end
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
