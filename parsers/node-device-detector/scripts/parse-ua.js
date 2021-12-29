#!/usr/bin/env node

const initStart = process.hrtime();
const DeviceDetector = require('node-device-detector');
const DeviceHelper = require('node-device-detector/helper');
const detector = new DeviceDetector();
// Trigger a parse to force cache loading
detector.detect('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('node-device-detector')) +
    '/package.json');
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
    const r = detector.detect(line);
    const bot = detector.parseBot(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result.parsed = {
        client: {
            name: bot === null ? (r.client.name ? r.client.name : null) : (bot.name ?? null),
            version: (bot !== null && r.client.version) ? r.client.version : null,
            isBot: bot !== null ? true : null,
            type: bot === null ? (r.client.type ?? null) : (bot.category ?? null)
        },
        platform: {
            name: r.os.name ? r.os.name : null,
            version: r.os.version ? r.os.version : null
        },
        device: {
            name: r.device.model ? r.device.model : null,
            brand: r.device.vendor ? r.device.vendor : null,
            type: r.device.type ? r.device.type : null,
            ismobile: DeviceHelper.isMobile(r) ? true : null,
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
