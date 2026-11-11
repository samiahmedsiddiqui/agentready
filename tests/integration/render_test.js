// Runs assets/js/verify.js's Markdown renderer against a minimal fake DOM, with a watchdog for hangs.
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const SCRIPT = process.env.VERIFY_JS || path.join(__dirname, 'wp-test', 'wp', 'wp-content', 'plugins', 'agentwelcome', 'assets', 'js', 'verify.js');

if (process.argv[2] !== 'child') {
  const run = spawnSync(process.execPath, [__filename, 'child'], { encoding: 'utf8', timeout: 15000 });
  if (run.error || run.status === null) {
    console.log('FAIL renderer did not finish within 15 s (infinite loop?)');
    console.log('RENDER RESULT: 0 passed, 1 failed');
    process.exit(1);
  }
  process.stdout.write(run.stdout);
  process.stderr.write(run.stderr);
  process.exit(run.status);
}

class Node_ {
  constructor(tag) { this.tag = tag; this.children = []; this.attrs = {}; this._text = ''; this.classList = { toggle() {}, add() {} }; }
  appendChild(c) { this.children.push(c); return c; }
  setAttribute(k, v) { this.attrs[k] = v; }
  set textContent(v) { this._text = v; this.children = []; }
  get textContent() { return this._text + this.children.map((c) => c.textContent).join(''); }
  find(tag, out = []) { this.children.forEach((c) => { if (c.tag === tag) out.push(c); c.find(tag, out); }); return out; }
  addEventListener() {}
}
global.document = { createElement: (t) => new Node_(t), createTextNode: (t) => { const n = new Node_('#text'); n._text = t; return n; }, addEventListener() {}, getElementById() { return null; }, querySelector() { return null; } };
global.window = {};
new Function('window', 'document', fs.readFileSync(SCRIPT, 'utf8'))(global.window, global.document);

let pass = 0;
let fail = 0;
const check = (name, ok, extra = '') => { ok ? pass++ : fail++; console.log((ok ? 'ok   ' : 'FAIL ') + name + (ok ? '' : '  ' + extra)); };
const render = (md) => { const root = new Node_('div'); window.agentwelcomeRenderMarkdown(md, root); return root; };

const JS = 'java' + 'script:alert(1)';
const SCRIPT_TAG = '<' + 'scr' + 'ipt>alert(1)<' + '/scr' + 'ipt>';
const IMG_TAG = '<' + 'img src=x on' + 'error="alert(1)">';

let r = render('# Title **bold** and `code`');
check('heading with bold and code, nested inline does not hang', r.find('h1').length === 1 && r.find('strong')[0].textContent === 'bold' && r.find('code')[0].textContent === 'code');

r = render('A **bold with *em* inside** and **two** and **three**.');
check('several nested emphasis runs in one line', r.find('strong').length === 3 && r.find('em').length === 1);

r = render('[safe](https://example.com/a) [rel](/about/) [bad](' + JS + ') [data](data:text/html,x) [mail](mailto:a@b.c)');
const links = r.find('a');
check('http, relative and mailto links get an href', [0, 1, 4].every((i) => links[i].attrs.href) && links[0].attrs.rel === 'noopener noreferrer');
check('javascript: and data: links get no href', !links[2].attrs.href && !links[3].attrs.href);

r = render('![alt text](x.png) and [![nested](y.png)](https://example.com)');
check('images become a text placeholder and no img element is created', r.find('img').length === 0 && r.textContent.includes('[image: alt text]'));

r = render(SCRIPT_TAG + '\n\n' + IMG_TAG);
check('HTML in the Markdown stays text, no elements are created', r.find('script').length === 0 && r.find('img').length === 0 && r.textContent.includes('alert(1)') && JSON.stringify(r).indexOf('onerror"') === -1);

r = render(['* one', '* two', '    * nested a', '    * nested b', '* three', '', '1. first', '2. second'].join('\n'));
check('nested and ordered lists', r.find('ul').length === 2 && r.find('ol').length === 1 && r.find('li').length === 7, r.find('li').length);

r = render('> quoted **text**\n> more');
check('blockquote with inline formatting', r.find('blockquote').length === 1 && r.find('strong').length === 1);

r = render(['| A | B |', '|---|---|', '| 1 | two |', '| 3 | four |'].join('\n'));
check('table', r.find('table').length === 1 && r.find('th').length === 2 && r.find('td').length === 4);

r = render(['| A \\| B | C |', '|---|---|', '| 1 \\| 2 | 3 |'].join('\n'));
check('an escaped pipe stays inside its table cell', r.find('th').length === 2 && r.find('th')[0].textContent === 'A | B' && r.find('td')[0].textContent === '1 | 2' && r.find('td')[1].textContent === '3', r.find('th').map((t) => t.textContent).join('/'));

r = render('```js\nvar a = "<b>x</b>";\n```\n\nafter');
check('fenced code keeps its text and ends at the closing fence', r.find('pre').length === 1 && r.find('code')[0].textContent === 'var a = "<b>x</b>";' && r.find('p')[0].textContent === 'after');

r = render('```\nnever closed\nstill code');
check('an unclosed fence runs to the end', r.find('pre').length === 1 && r.find('code')[0].textContent.includes('still code'));

r = render('---\ntitle: "T"\nurl: "http://x"\n---\n# Body');
check('front matter goes into a Metadata block', r.find('details').length === 1 && r.find('pre')[0].textContent.includes('title: "T"') && r.find('h1').length === 1);

r = render('One\n\n---\n\nTwo');
check('a rule between paragraphs is not front matter', r.find('hr').length === 1 && r.find('p').length === 2);

r = render('Escaped \\*star\\* and *em* end.');
check('escapes and emphasis', r.find('p')[0].textContent === 'Escaped *star* and em end.' && r.find('em').length === 1, r.find('p')[0].textContent);

r = render('Line one\nline two\n\nNext');
check('lines of a paragraph are joined', r.find('p')[0].textContent === 'Line one line two' && r.find('p').length === 2);

r = render('');
check('empty input', r.children.length === 0);

check('the script uses no regex lookbehind or lookahead, which older Safari cannot parse', !/\(\?<[=!]/.test(fs.readFileSync(SCRIPT, 'utf8')));

const big = Array.from({ length: 3000 }, (_, i) => '- item **' + i + '** with [link](https://example.com/' + i + ')').join('\n');
const t0 = Date.now();
r = render(big);
check('3000 list items render quickly', r.find('li').length === 3000 && Date.now() - t0 < 3000, Date.now() - t0);

console.log('RENDER RESULT: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
