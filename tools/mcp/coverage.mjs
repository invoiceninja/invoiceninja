import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { requiredScenarios } from './required-scenarios.mjs';

// An alternate artifact directory allows testing the actual CLI without demo credentials.
const local = process.argv[2] ?? fileURLToPath(new URL('../../.local/', import.meta.url));
const failures = [];
function read(name, fallback) {
  try {
    return JSON.parse(fs.readFileSync(path.join(local, name), 'utf8'));
  } catch (error) {
    failures.push({source: name, message: `Required artifact could not be read: ${error.message}`});
    return fallback;
  }
}
const inventory = read('api-manifest.json', []);
const catalog = read('catalog.json', {});
const operations = read('full-e2e-results.json', []);
const scenarios = read('e2e-results.json', {}).results ?? [];
const expectedOperations = Object.entries(catalog).flatMap(([tool, entries]) => Object.keys(entries).map(operation => `${tool}.${operation}`));
if (!inventory.length || !expectedOperations.length) {
  failures.push({source: 'inventory', message: 'A nonempty route inventory and operation catalog are required.'});
}

function checkResults(rows, expected, key, source) {
  const outcomes = new Map();
  for (const row of rows) {
    const name = key(row);
    if (outcomes.has(name)) failures.push({source, name, message: 'Duplicate scenario result.'});
    outcomes.set(name, row);
    if (row.result !== 'passed') failures.push({source, name, message: row.message ?? `Scenario result: ${row.result}`});
  }
  for (const name of expected) {
    if (!outcomes.has(name)) failures.push({source, name, message: 'Required scenario is missing; validation is incomplete.'});
  }
  return outcomes;
}
const outcomes = checkResults(operations, expectedOperations, row => `${row.tool}.${row.operation}`, 'full-e2e-results.json');
const protocol = checkResults(scenarios, requiredScenarios(catalog), row => row.scenario, 'e2e-results.json');
const protocolPassed = ['protocol.initialize', 'protocol.tools_list'].every(name => protocol.get(name)?.result === 'passed');

for (const row of inventory) {
  if (row.api.endsWith(' api/v1/mcp')) {
    row.tool = null;
    row.exclusion = null;
    row.classification = 'protocol';
    row.test = 'McpApiTest protocol/HTTP tests; e2e.mjs SDK connection';
    row.result = protocolPassed ? 'protocol_tested' : 'pending';
    continue;
  }
  row.classification = row.tool ? 'business' : 'excluded';
  if (row.tool) {
    const outcome = outcomes.get(row.tool);
    row.test = {docker: `tools/mcp/full-e2e.mjs: ${row.tool}`, repository: 'tests/Feature/Mcp: authentication, permissions, REST equivalence and workflow persistence'};
    row.result = outcome?.result ?? 'pending';
    if (row.api.startsWith('PATCH ')) row.api_alias_of = row.api.replace('PATCH ', 'PUT ');
    if (row.tool.endsWith('.bulk') || row.tool.endsWith('.action')) row.excluded_file_actions = ['download', 'bulk_download', 'bulk_print', 'delivery_note', 'print', 'template'];
    if (outcome?.message) row.failure = outcome.message;
    if (row.result !== 'passed') failures.push({source: 'api-manifest.json', name: row.api, message: `Mapped operation ${row.tool} has not passed.`});
  }
}
const summary = {
  api_verbs: inventory.length,
  business_verbs: inventory.filter(row => row.classification === 'business').length,
  excluded_verbs: inventory.filter(row => row.classification === 'excluded').length,
  protocol_verbs: inventory.filter(row => row.classification === 'protocol').length,
  tools: Object.keys(catalog).length,
  operations: operations.length,
  passed: [...operations, ...scenarios].filter(row => row.result === 'passed').length,
  failed: [...operations, ...scenarios].filter(row => row.result !== 'passed').length,
  operations_passed: operations.filter(row => row.result === 'passed').length,
  operations_failed: operations.filter(row => row.result !== 'passed').length,
  scenarios: scenarios.length,
  scenarios_passed: scenarios.filter(row => row.result === 'passed').length,
  scenarios_failed: scenarios.filter(row => row.result !== 'passed').length,
  validation: failures.length ? 'failed_or_incomplete' : 'passed',
};
fs.writeFileSync(path.join(local, 'coverage.json'), JSON.stringify({summary, failures, scenarios, routes: inventory}, null, 2) + '\n');
console.log(JSON.stringify({summary, failures}, null, 2));
process.exitCode = failures.length ? 1 : 0;
