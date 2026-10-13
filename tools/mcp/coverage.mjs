import fs from 'node:fs';
const root=new URL('../../',import.meta.url);
const local=new URL('.local/',root);
const inventory=JSON.parse(fs.readFileSync(new URL('api-manifest.json',local)));
const results=JSON.parse(fs.readFileSync(new URL('full-e2e-results.json',local)));
const outcomes=new Map(results.map(r=>[`${r.tool}.${r.operation}`,r]));
const assistant=JSON.parse(fs.readFileSync(new URL('tools/mcp/assistant-results.json',root)));
const assistantOutcomes=new Map(assistant.results.map(r=>[`${r.tool}.${r.operation}`,r]));
for(const row of inventory) {
  if(row.api.endsWith(' api/v1/mcp')) {
    row.tool=null;row.exclusion=null;row.classification='protocol';row.test='McpApiTest protocol/HTTP tests; e2e.mjs SDK connection';row.result='protocol_tested';
    continue;
  }
  row.classification=row.tool?'business':'excluded';
  if(row.tool) {
    const outcome=outcomes.get(row.tool);
    row.test={docker:`tools/mcp/full-e2e.mjs: ${row.tool}`,repository:'tests/Feature/Mcp/McpApiTest.php: shared authentication/permissions and business-domain list equivalence'};
    row.result=outcome?.result ?? 'pending';
    row.assistant_result=assistantOutcomes.get(row.tool)?.result ?? 'pending';
    row.test.assistant=`tools/mcp/assistant-results.json: ${row.tool} (native Codex invocation)`;
    if(row.api.startsWith('PATCH '))row.api_alias_of=row.api.replace('PATCH ','PUT ');
    if(row.tool.endsWith('.bulk')||row.tool.endsWith('.action'))row.excluded_file_actions=['download','bulk_download','bulk_print','delivery_note','print'];
    if(outcome?.message)row.failure=outcome.message;
  }
}
const summary={api_verbs:inventory.length,business_verbs:inventory.filter(r=>r.classification==='business').length,excluded_verbs:inventory.filter(r=>r.classification==='excluded').length,protocol_verbs:inventory.filter(r=>r.classification==='protocol').length,tools:new Set(results.map(r=>r.tool)).size,operations:results.length,passed:results.filter(r=>r.result==='passed').length,failed:results.filter(r=>r.result==='failed').length};
summary.assistant={tools:assistant.tools,operations:assistant.operations,passed:assistant.passed,failed:assistant.failed,client:'native Codex tools',report:'tools/mcp/assistant-results.json'};
fs.writeFileSync(new URL('tools/mcp/api-manifest.json',root),JSON.stringify({summary,routes:inventory},null,2)+'\n');
console.log(JSON.stringify(summary,null,2));
if(summary.failed||assistant.failed||inventory.some(r=>r.classification==='business'&&(r.result!=='passed'||r.assistant_result!=='passed')))process.exitCode=1;
