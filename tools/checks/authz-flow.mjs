#!/usr/bin/env node
/**
 * HTTP-level authorization proof.
 *
 * Logs in as a user holding only the `viewer` role and then requests every
 * privileged URL *directly*. If hiding links were the only control, these
 * would all succeed; they must return 403.
 */

import { Flow, Checks } from './lib/flow-harness.mjs';

// ISOLATION: private database, private viewer account. The viewer's password is
// generated per run, so this flow cannot depend on (or disturb) the account
// another flow used.
const flow = new Flow('authz');
await flow.setup();

const c = new Checks('authz-flow');

const USER = 'viewer1';
const PASSWORD = `Viewer-${Date.now().toString(36)}-D5`;
await flow.resetPassword({ username: USER, password: PASSWORD, mustChange: false });

const results = c.results;
const check = (id, ok, detail) => c.check(id, ok, detail);
const render = (spec) => flow.render(spec);

// log in as the viewer
const page = await render({ uri: '/login' });
const token = (page.body.match(/name="_token" value="([^"]+)"/) || [])[1];
const login = await render({
  method: 'POST', uri: '/login',
  body: `_token=${encodeURIComponent(token)}&username=${USER}&password=${encodeURIComponent(PASSWORD)}`,
});
check('T-AZ-00', login.status === 302 && !/\/login/.test(login.headers.location || ''),
  `viewer login -> ${login.status} -> ${login.headers.location}`);

// allowed pages
for (const [id, uri] of [['T-AZ-01', '/customers'], ['T-AZ-02', '/contracts'], ['T-AZ-03', '/']]) {
  const r = await render({ uri });
  check(id, r.status === 200, `allowed GET ${uri} -> ${r.status}`);
}

// the create link must NOT be in the HTML (UI respects permission)
const list = await render({ uri: '/customers' });
const hasCreateLink = /href="http:\/\/localhost:8080\/customers\/create"/.test(list.body);
check('T-AZ-04', !hasCreateLink, `create button hidden for viewer: ${!hasCreateLink}`);

// explicit API-level refusals: direct URL access
for (const [id, uri, label] of [
  ['T-AZ-05', '/customers/create', 'customer create form'],
  ['T-AZ-06', '/contracts/create', 'contract create form'],
  ['T-AZ-07', '/password', null], // allowed, sanity that we're authenticated
]) {
  const r = await render({ uri });
  if (label === null) { check(id, r.status === 200, `GET ${uri} -> ${r.status}`); continue; }
  const refused = r.status === 403 && /دسترسی غیرمجاز/.test(r.body);
  check(id, refused, `direct GET ${uri} -> ${r.status}, persian 403 = ${/دسترسی غیرمجاز/.test(r.body)}`);
}

// POST must be refused too, not just the form
const postToken = ((await render({ uri: '/customers' })).body.match(/name="_token" value="([^"]+)"/) || [])[1] || token;
const post = await render({
  method: 'POST', uri: '/customers',
  body: `_token=${encodeURIComponent(postToken)}&type=legal&company_name=Hack+Co&status=lead`,
});
check('T-AZ-08', post.status === 403, `direct POST /customers -> ${post.status}`);

// the manager role must be refused financial data it lacks
const del = await render({ method: 'POST', uri: '/customers/1', body: `_token=${encodeURIComponent(postToken)}&_method=DELETE` });
check('T-AZ-09', del.status === 403, `direct DELETE /customers/1 -> ${del.status}`);

// The viewer really holds only the viewer role in this database.
const roles = await flow.query(
  "select r.code from roles r join user_roles ur on ur.role_id = r.id join users u on u.id = ur.user_id where u.username = 'viewer1' order by r.code"
);
check('T-AZ-10', roles.trim() === 'viewer', `viewer1 roles in flow db: "${roles.trim()}"`);

const ok = c.summary();
flow.cleanup();
process.exit(ok ? 0 : 1);
