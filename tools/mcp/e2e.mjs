import assert from 'node:assert/strict';
import fs from 'node:fs';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const root = new URL('../../', import.meta.url);
const local = new URL('.local/', root);
const fixture = JSON.parse(fs.readFileSync(new URL('fixture.json', local)));
const catalog = JSON.parse(fs.readFileSync(new URL('catalog.json', local)));
const endpoint = process.env.MCP_TEST_URL ?? 'http://127.0.0.1:18081/api/v1/mcp';
assert.match(endpoint, /^https?:\/\/(127\.0\.0\.1|localhost|100\.99\.75\.75|app)(:18081|:8000)?\//, 'Only the explicitly isolated local fixture is allowed');
const client = new Client({name:'invoice-ninja-upstream-e2e',version:'1.0.0'});
const transport = new StreamableHTTPClientTransport(new URL(endpoint), {requestInit:{headers:{Authorization:`Bearer ${fixture.tokens.alpha.admin}`}}});
const results = [];
async function scenario(name, fn) {
  try { const detail = await fn(); results.push({scenario:name,result:'passed',...detail}); }
  catch (error) { results.push({scenario:name,result:'failed',message:String(error.message)}); }
  fs.writeFileSync(new URL('e2e-results.json', local), JSON.stringify({endpoint,results},null,2));
}
function body(result) {
  assert.equal(result.isError,false, JSON.stringify(result.content));
  const value = result.structuredContent ?? JSON.parse(result.content[0].text);
  assert.equal(value.status,200, JSON.stringify(value));
  return value.body;
}
try {
  await client.connect(transport);
  await scenario('protocol.initialize', async()=>{ assert.equal(client.getServerVersion().name,'Invoice Ninja'); return {}; });
  let tools = [], cursor;
  do { const page = await client.listTools(cursor ? {cursor} : {}); tools.push(...page.tools); cursor=page.nextCursor; } while(cursor);
  await scenario('protocol.tools_list', async()=>{ assert.deepEqual(tools.map(t=>t.name).sort(), Object.keys(catalog).sort()); return {count:tools.length}; });
  for (const tool of tools) {
    const operation = tool.name === 'charts' ? 'totals' : tool.name === 'emails' ? 'client_history' : 'index';
    await scenario(`${tool.name}.${operation}`, async()=>{
      const args = {operation};
      if (tool.name === 'emails') args.parameters={client:fixture.entities.alpha.clients};
      if (tool.name === 'charts') args.data={date_range:'all_time',include_drafts:true};
      const value = body(await client.callTool({name:tool.name,arguments:args}));
      assert.ok(value !== null);
      if (Array.isArray(value?.data)) {
        const foreign = fixture.entities.beta[tool.name];
        if (foreign) assert.ok(!value.data.some(row=>row.id===foreign), 'Other company record leaked');
      }
      return {status:200};
    });
  }
  await scenario('clients.create_update_read_database', async()=>{
    const created=body(await client.callTool({name:'clients',arguments:{operation:'store',data:{name:'MCP E2E Created',contacts:[{email:'e2e@example.invalid'}]}}}));
    const id=created.data.id;
    body(await client.callTool({name:'clients',arguments:{operation:'update',parameters:{client:id},data:{name:'MCP E2E Updated'}}}));
    const read=body(await client.callTool({name:'clients',arguments:{operation:'show',parameters:{client:id}}}));
    assert.equal(read.data.name,'MCP E2E Updated');
    return {id};
  });
  await scenario('tasks.time_tracking_update', async()=>{
    const id=fixture.entities.alpha.tasks;
    body(await client.callTool({name:'tasks',arguments:{operation:'update',parameters:{task:id},data:{time_log:JSON.stringify([[1768471200,1768478400]]),description:'MCP E2E Tracked'}}}));
    const task=body(await client.callTool({name:'tasks',arguments:{operation:'show',parameters:{task:id}}}));
    assert.equal(task.data.description,'MCP E2E Tracked');
    const logs=JSON.parse(task.data.time_log);
    assert.equal(logs[0][1]-logs[0][0],7200);
    return {status:200};
  });
} finally { await client.close(); }
const failed=results.filter(r=>r.result==='failed');
console.log(JSON.stringify({passed:results.length-failed.length,failed:failed.length,failures:failed},null,2));
if (failed.length) process.exitCode=1;
