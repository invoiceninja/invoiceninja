// Required independently of the result files, so interrupted runs cannot look complete.
export function requiredScenarios(catalog) {
  return [
    'protocol.initialize',
    'protocol.tools_list',
    'security.normalized_inputs',
    'security.route_collisions_rejected',
    'security.singular_company_includes_rejected',
    'security.present_invalid_origin_rejected',
    'security.first_load_rejected',
    'security.matching_host_origin_rejected',
    'protocol.unimplemented_actions_rejected',
    ...Object.keys(catalog).map(tool => `${tool}.${tool === 'charts' ? 'totals' : tool === 'emails' ? 'client_history' : 'index'}`),
    'clients.create_update_read_database',
    'tasks.time_tracking_update',
    'invoices.payment_and_refund',
    'quotes.convert_to_invoice',
  ];
}
