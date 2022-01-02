#!/usr/bin/env node

const initStart = process.hrtime();
const useragent = require('@financial-times/useragent_parser');
// Trigger a parse to force cache loading
useragent('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('@financial-times/useragent_parser')) +
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
    const r = useragent(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        client: {
            name: r.family ? r.family : null,
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
        raw: r
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
