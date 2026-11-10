import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';
import { requiredScenarios } from './required-scenarios.mjs';

function run(t, modify = () => {}) {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'mcp-coverage-'));
  t.after(() => fs.rmSync(directory, {recursive: true, force: true}));
  const catalog = {clients: {index: {}}};
  const artifacts = {
    'catalog.json': catalog,
    'api-manifest.json': [{api: 'GET api/v1/clients', tool: 'clients.index'}, {api: 'POST api/v1/mcp', tool: null}],
    'full-e2e-results.json': [{tool: 'clients', operation: 'index', result: 'passed'}],
    'e2e-results.json': {results: requiredScenarios(catalog).map(scenario => ({scenario, result: 'passed'}))},
  };
  modify(artifacts);
  for (const [name, value] of Object.entries(artifacts)) fs.writeFileSync(path.join(directory, name), JSON.stringify(value));
  const child = spawnSync(process.execPath, [fileURLToPath(new URL('./coverage.mjs', import.meta.url)), directory], {encoding: 'utf8'});
  assert.equal(child.error, undefined);
  const report = JSON.parse(fs.readFileSync(path.join(directory, 'coverage.json'), 'utf8'));
  return {status: child.status, report};
}

test('complete successful results pass the actual coverage CLI', t => {
  const {status, report} = run(t);
  assert.equal(status, 0);
  assert.equal(report.summary.validation, 'passed');
  assert.deepEqual(report.failures, []);
});

test('a failed security scenario produces a nonzero exit code and appears in the report', t => {
  const {status, report} = run(t, artifacts => {
    const result = artifacts['e2e-results.json'].results.find(row => row.scenario === 'security.normalized_inputs');
    Object.assign(result, {result: 'failed', message: 'Reserved selector reached REST'});
  });
  assert.equal(status, 1);
  assert.equal(report.summary.scenarios_failed, 1);
  assert.equal(report.summary.failed, 1);
  assert.ok(report.failures.some(row => row.name === 'security.normalized_inputs' && row.message === 'Reserved selector reached REST'));
  assert.ok(report.scenarios.some(row => row.scenario === 'security.normalized_inputs' && row.result === 'failed'));
});

test('missing required scenarios make validation incomplete', t => {
  const {status, report} = run(t, artifacts => {
    artifacts['e2e-results.json'].results = artifacts['e2e-results.json'].results.filter(row => row.scenario !== 'security.first_load_rejected');
  });
  assert.equal(status, 1);
  assert.ok(report.failures.some(row => row.name === 'security.first_load_rejected' && row.message.includes('incomplete')));
});

test('missing operations make validation incomplete', t => {
  const {status, report} = run(t, artifacts => { artifacts['full-e2e-results.json'] = []; });
  assert.equal(status, 1);
  assert.ok(report.failures.some(row => row.name === 'clients.index' && row.message.includes('incomplete')));
});

test('failures from both result files are retained', t => {
  const {status, report} = run(t, artifacts => {
    artifacts['full-e2e-results.json'][0].result = 'failed';
    artifacts['e2e-results.json'].results[0].result = 'failed';
  });
  assert.equal(status, 1);
  for (const source of ['full-e2e-results.json', 'e2e-results.json']) assert.ok(report.failures.some(row => row.source === source));
});

test('a missing result file cannot reuse a previous successful report', t => {
  const {status, report} = run(t, artifacts => { delete artifacts['e2e-results.json']; });
  assert.equal(status, 1);
  assert.equal(report.summary.validation, 'failed_or_incomplete');
  assert.ok(report.failures.some(row => row.source === 'e2e-results.json' && row.message.includes('could not be read')));
});
