#!/usr/bin/env node

const initStart = process.hrtime();
const parser = require('woothee');
// Trigger a parse to force cache loading
parser.parse('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('woothee')) +
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
    const r = parser.parse(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        client: {
            name: (r.name && r.name !== 'UNKNOWN') ? r.name : null,
            version: (r.version && r.version !== 'UNKNOWN') ? r.version : null,
            isBot: null,
            type: null
        },
        platform: {
            name: (r.os && r.os !== 'UNKNOWN') ? r.os : null,
            version: (r.os_version && r.os_version !== 'UNKNOWN') ? r.os_version : null
        },
        device: {
            name: null,
            brand: (r.vendor && r.vendor !== 'UNKNOWN') ? r.vendor : null,
            type: (r.category && r.category !== 'UNKNOWN') ? r.category : null,
            ismobile: null,
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
