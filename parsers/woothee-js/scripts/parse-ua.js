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
            client: {
                name: r.name,
                version: r.version,
                isBot: null,
                type: null
            },
            platform: {
                name: r.os,
                version: r.os_version
            },
            device: {
                name: null,
                brand: null,
                type: r.category,
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
