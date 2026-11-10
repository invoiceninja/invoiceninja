import fs from 'node:fs';
const fixture=JSON.parse(fs.readFileSync(new URL('../../.local/fixture.json',import.meta.url)));
const ids=fixture.entities.alpha;
const nextDate=new Date(Date.now()+7*86400000).toISOString().slice(0,10);
const line={quantity:1,cost:100,product_key:'Demo service',notes:'Synthetic only'};
export function createData(domain, suffix) {
  const name=`MCP E2E ${domain} ${suffix}`;
  switch(domain) {
    case 'clients': case 'vendors': return {name,contacts:[{email:`${domain}@example.invalid`,first_name:'Demo'}]};
    case 'invoices': case 'quotes': case 'credits': return {client_id:ids.clients,line_items:[line],date:'2026-01-15'};
    case 'purchase_orders': return {vendor_id:ids.vendors,line_items:[line],date:'2026-01-15'};
    case 'recurring_invoices': case 'recurring_quotes': return {client_id:ids.clients,line_items:[line],frequency_id:5,next_send_date:nextDate,remaining_cycles:2};
    case 'expenses': return {client_id:ids.clients,vendor_id:ids.vendors,amount:25,currency_id:1,date:'2026-01-15'};
    case 'recurring_expenses': return {client_id:ids.clients,vendor_id:ids.vendors,amount:25,currency_id:1,frequency_id:5,remaining_cycles:2,next_send_date:nextDate};
    case 'payments': return {client_id:ids.clients,amount:10,payment_type_id:1,date:'2026-01-15'};
    case 'products': return {product_key:name,price:25,cost:5,notes:'Synthetic'};
    case 'projects': return {name,client_id:ids.clients,task_rate:50};
    case 'tasks': return {client_id:ids.clients,project_id:ids.projects,description:name,time_log:JSON.stringify([[1768471200,1768474800]])};
    case 'tax_rates': return {name,rate:10};
    case 'payment_terms': return {num_days:30};
    case 'expense_categories': case 'group_settings': case 'task_statuses': return {name};
    case 'locations': return {name,client_id:ids.clients,country_id:840};
    case 'tags': return {name,entity_type:'App\\Models\\Client'};
    case 'subscriptions': return {name,steps:'cart,auth.login-or-register',frequency_id:5};
    case 'bank_transactions': return {bank_integration_id:ids.bank_integrations,amount:25,base_type:'CREDIT',date:'2026-01-15',description:name};
    case 'bank_transaction_rules': return {name,rules:[],matches_on_all:true,auto_convert:false};
    case 'designs': return {name,design:{header:'<div>Demo</div>',body:'<div>$invoice.number</div>',footer:'',includes:'',customCss:''}};
    case 'webhooks': return {target_url:'https://mcp-webhook.example.invalid/synthetic',event_id:21,rest_method:'post'};
    default: throw new Error(`No create scenario for ${domain}`);
  }
}
export function updateData(domain, suffix) {
  const name=`MCP E2E Update ${suffix}`;
  switch(domain) {
    case 'invoices': case 'quotes': case 'credits': case 'purchase_orders': case 'recurring_invoices': case 'recurring_quotes': return {public_notes:name};
    case 'payments': return {private_notes:name};
    case 'expenses': case 'recurring_expenses': return {private_notes:name};
    case 'products': return {notes:name};
    case 'tasks': return {description:name};
    case 'bank_transactions': return {description:name,date:'2026-01-15',bank_integration_id:ids.bank_integrations};
    case 'locations': return {name,client_id:ids.clients};
    case 'payment_terms': return {num_days:45};
    case 'subscriptions': return {name,steps:'cart,auth.login-or-register',frequency_id:5};
    case 'webhooks': return {target_url:'https://mcp-webhook.example.invalid/updated',event_id:21,rest_method:'post'};
    default: return {name};
  }
}
export function argumentsFor(domain,operation,spec,id,suffix) {
  const parameters={};
  for (const key of spec.parameters) parameters[key]=key==='action'?'mark_sent':domain==='charts' && key==='project'?ids.projects:id;
  let data={};
  if (operation==='store') data=createData(domain,suffix);
  if (operation==='update') data=updateData(domain,suffix);
  if (operation==='bulk') data={ids:[id],action:'archive'};
  if (domain==='charts') data={date_range:'all_time',include_drafts:true};
  if (operation==='calculated_fields') Object.assign(data,{field:'active_invoices',calculation:'count',period:'total'});
  if (domain==='activities' && operation==='entity_activity') data={entity:'client',entity_id:ids.clients};
  if (domain==='activities' && operation==='note') data={entity:'clients',entity_id:ids.clients,notes:'MCP E2E Note'};
  if (domain==='search') data={search:'Demo'};
  if (domain==='emails' && operation==='send') data={entity:'invoice',entity_id:ids.invoices,template:'email_template_invoice',subject:'Synthetic MCP test',body:'Demo data only.'};
  if (domain==='emails' && operation==='entity_history') data={entity:'invoice',entity_id:ids.invoices};
  if (domain==='emails' && operation==='client_history') parameters.client=ids.clients;
  if (operation==='payment_schedule') data={next_run:nextDate,frequency_id:5,remaining_cycles:1,parameters:{invoice_id:id,auto_bill:false}};
  if (operation==='sort') data={status_ids:[ids.task_statuses],task_ids:[ids.tasks]};
  if (operation==='match') data={transactions:[{id:ids.bank_transactions,client_id:ids.clients,vendor_id:ids.vendors}]};
  if (operation==='refund') data={id:ids.payments,amount:1,date:'2026-01-16',gateway_refund:false,send_email:false};
  if (operation==='default') data={design_id:id,entity:'invoice'};
  if (operation==='documents') data={};
  return {operation,parameters,data,query:operation==='index'?{per_page:2}:undefined};
}
