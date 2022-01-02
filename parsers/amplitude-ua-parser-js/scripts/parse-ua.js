#!/usr/bin/env node

const initStart = process.hrtime();
const parser = require('@amplitude/ua-parser-js');
// Trigger a parse to force cache loading
parser('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('@amplitude/ua-parser-js')) +
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
    const r = parser(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        client: {
            name: r.browser.name ? r.browser.name : null,
            version: r.browser.version ? r.browser.version : null,
            isBot: null,
            type: null
        },
        platform: {
            name: r.os.name ? r.os.name : null,
            version: r.os.version ? r.os.version : null
        },
        device: {
            name: r.device.model ? r.device.model : null,
            brand: r.device.vendor ? r.device.vendor : null,
            type: r.device.type ? r.device.type : null,
            ismobile:
                r.device.type === 'mobile' ||
                r.device.type === 'tablet' ||
                r.device.type === 'wearable',
            istouch: null
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
