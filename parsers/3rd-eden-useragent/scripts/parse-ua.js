const initStart = process.hrtime();
const parser = require('useragent');
parser(true);

// Trigger a parse to force cache loading
parser.parse('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(require.resolve('useragent')) +
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
    const r = parser.parse(line),
        os = r.os,
        device = r.device;
    const end = process.hrtime(start)[1] / 1000000000;

    const outputDevice = {
        name: null,
        brand: null,
        type: null,
        ismobile: null,
        istouch: null
    };

    if (device.major !== '0') {
        outputDevice.name = device.major;
        outputDevice.brand = device.family;
    } else if (device.family !== 'Other') {
        outputDevice.name = device.family;
    }

    output.result.parsed = {
        client: {
            name: r.family === 'Other' ? null : r.family,
            version: (r.family === 'Other' || r.toVersion() === '0.0.0') ? null : r.toVersion(),
            isBot: null,
            type: null
        },
        platform: {
            name: os.family === 'Other' ? null : os.family,
            version: (os.family === 'Other' || r.os.toVersion() === '0.0.0') ? null : r.os.toVersion()
        },
        device: outputDevice,
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
