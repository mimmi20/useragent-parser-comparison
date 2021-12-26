const initStart = process.hrtime();
const WhichBrowser = require('which-browser');
// Trigger a parse to force cache loading
new WhichBrowser('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const packageInfo = require(require('path').dirname(
    require.resolve('which-browser')
) + '/../package.json');
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
    let result = {};

    try {
        const r = new WhichBrowser(line);
    } catch (err) {
        output.result = {
            useragent: line,
            parsed: {
                client: {
                    name: null,
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
                raw: null
            }
        };
    }
    const end = process.hrtime(start)[1] / 1000000000;

    const mobileDeviceTypes = [
        'mobile',
        'tablet',
        'watch',
        'media',
        'ereader',
        'camera'
    ];

    if (typeof r !== 'undefined') {
        output.result = {
            useragent: line,
            parsed: {
                client: {
                    name: r.browser.name ? r.browser.name : null,
                    version: r.browser.version ? r.browser.version.value : null,
                    isBot: null,
                    type: null
                },
                platform: {
                    name: r.os.name ? r.os.name : null,
                    version:
                        r.os.version && r.os.version.value ? r.os.version.value : null
                },
                device: {
                    name: r.device.model ? r.device.model : null,
                    brand: r.device.manufacturer ? r.device.manufacturer : null,
                    type: r.device.type ? r.device.type : null,
                    ismobile:
                        mobileDeviceTypes.indexOf(r.device.type) !== -1 ||
                        (r.device.subtype && r.device.subtype === 'portable')
                            ? true
                            : false,
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
    }
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
