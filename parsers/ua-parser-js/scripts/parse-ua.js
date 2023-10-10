#!/usr/bin/env node

const initStart = process.hrtime();
const UAParser = require('ua-parser-js');
const parser = new UAParser();
// Trigger a parse to force cache loading
parser.setUA('Test String');
parser.getResult();
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('ua-parser-js')) +
    '/../package.json');
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
    headers: {
        "user-agent": line
    },
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
    parser.setUA(line);
    const r = parser.getResult();
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        device: {
            architecture: null,
            deviceName: r.device.model ? r.device.model : null,
            marketingName: null,
            manufacturer: null,
            brand: r.device.vendor ? r.device.vendor : null,
            display: {
                width: null,
                height: null,
                touch: null,
                type: null,
                size: null,
            },
            dualOrientation: null,
            type: r.device.type ? r.device.type : null,
            simCount: null,
            ismobile:
                r.device.type === 'mobile' ||
                r.device.type === 'tablet' ||
                r.device.type === 'wearable',
            istv: null,
            bits: null
        },
        client: {
            name: r.browser.name ? r.browser.name : null,
            modus: null,
            version: r.browser.version ? r.browser.version : null,
            manufacturer: null,
            bits: null,
            type: null,
            isbot: null
        },
        platform: {
            name: r.os.name ? r.os.name : null,
            marketingName: null,
            version: r.os.version ? r.os.version : null,
            manufacturer: null,
            bits: null
        },
        engine: {
            name: null,
            version: null,
            manufacturer: null
        },
        raw: r
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
