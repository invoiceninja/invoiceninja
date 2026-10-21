import assert from 'node:assert/strict';
import fs from 'node:fs';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import { argumentsFor } from './scenarios.mjs';
const local=new URL('../../.local/',import.meta.url);
const fixture=JSON.parse(fs.readFileSync(new URL('fixture.json',local)));
const catalog=JSON.parse(fs.readFileSync(new URL('catalog.json',local)));
const client=new Client({name:'invoice-ninja-complete-catalog-test',version:'1.0.0'});
const url=process.env.MCP_TEST_URL ?? 'http://app:8000/api/v1/mcp';
assert.equal(new URL(url).hostname,'app','Full mutation suite only runs on the internal Docker fixture network');
await client.connect(new StreamableHTTPClientTransport(new URL(url),{requestInit:{headers:{Authorization:`Bearer ${fixture.tokens.alpha.admin}`}}}));
const results=[];
const suffix=Date.now();
const order=['index','create','store','show','edit','update','action','payment_schedule','delete_payment_schedule','bulk','destroy'];
try {
  for(const [domain,operations] of Object.entries(catalog)) {
    let id=fixture.entities.alpha[domain];
    const names=Object.keys(operations).sort((a,b)=> (order.indexOf(a)<0?6:order.indexOf(a))-(order.indexOf(b)<0?6:order.indexOf(b)));
    for(const operation of names) {
      const row={tool:domain,operation,api:`${operations[operation].method} ${operations[operation].uri}`,result:'pending'};
      try {
        const args=argumentsFor(domain,operation,operations[operation],id,suffix);
        if(domain==='payments' && operation==='refund') args.data.id=id;
        const reply=await client.callTool({name:domain,arguments:args});
        const value=reply.structuredContent ?? (reply.content?.[0]?.text ? JSON.parse(reply.content[0].text):null);
        assert.equal(reply.isError,false,JSON.stringify(value ?? reply.content));
        assert.ok(value && value.status>=200 && value.status<300,JSON.stringify(value));
        if(operation==='store') { assert.equal(typeof value.body.data.id,'string'); id=value.body.data.id; }
        if(operation==='show' || operation==='edit' || operation==='update') {
          if(value.body?.data?.id) assert.equal(value.body.data.id,id);
        }
        if(operation==='update') {
          for(const [field,expected] of Object.entries(args.data)) {
            if(value.body?.data && typeof expected!=='object') {
              assert.ok(Object.hasOwn(value.body.data,field),`Updated field is absent: ${domain}.${field}`);
              assert.equal(String(value.body.data[field]),String(expected),`Updated ${domain}.${field}`);
            }
          }
        }
        row.result='passed'; row.status=value.status;
      } catch(error) { row.result='failed'; row.message=String(error.message); }
      results.push(row);
      fs.writeFileSync(new URL('full-e2e-results.json',local),JSON.stringify(results,null,2));
    }
  }
} finally { await client.close(); }
const failures=results.filter(r=>r.result==='failed');
console.log(JSON.stringify({passed:results.length-failures.length,failed:failures.length,failures},null,2));
process.exitCode=failures.length?1:0;
