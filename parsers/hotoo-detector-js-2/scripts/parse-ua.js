const initStart = process.hrtime();
const parser = require('detector');
// Trigger a parse to force cache loading
parser.parse('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(require.resolve('detector')) +
    '/../package.json');
const version = package.version;

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
    const r = parser.parse(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result = {
        useragent: line,
        parsed: {
            browser: {
                name: r.browser.name ? r.browser.name : null,
                version: r.browser.fullVersion ? r.browser.fullVersion : null
            },
            platform: {
                name: r.os.name ? r.os.name : null,
                version: r.os.fullVersion ? r.os.fullVersion : null
            },
            device: {
                name: r.device.model ? r.device.model : null,
                brand: null,
                type: null,
                ismobile: null
            }
        },
        time: end
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
