const initStart = process.hrtime();
const parser = require('express-useragent');
// Trigger a parse to force cache loading
parser.parse('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(
    require.resolve('express-useragent')
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
    hasUa: hasUa,
    ua: line,
    result: {
        parsed: null,
        err: null
    },
    parse_time: 0,
    init_time: initTime,
    memory_used: 0,
    version: version
};

if (hasUa) {
    const start = process.hrtime();
    const r = parser.parse(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        client: {
            name: (r.browser && r.browser !== 'unknown') ? r.browser : null,
            version: (r.version && r.version !== 'unknown') ? r.version : null,
            isBot: null,
            type: null
        },
        platform: {
            name: (r.os && r.os !== 'unknown') ? r.os : null,
            version: null
        },
        device: {
            name: null,
            brand: null,
            type: null,
            ismobile: r.isMobile,
            istouch: null,
        },
        engine: {
            name: null,
            version: null
        },
        raw: r
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));