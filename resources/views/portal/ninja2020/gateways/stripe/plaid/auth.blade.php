<button id="link-button">Link Account</button>

<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
<script type="text/javascript">
(async function() {

  const configs = {
    // Pass the link_token generated in step 2.
    token: '{{ $link_token }}',
    onLoad: function() {
      // The Link module finished loading.
    },
    onSuccess: function(public_token, metadata) {
      // The onSuccess function is called when the user has
      // successfully authenticated and selected an account to
      // use.
      //
      // When called, you will send the public_token
      // and the selected account ID, metadata.accounts,
      // to your backend app server.
      //
      // sendDataToBackendServer({
      //   public_token: public_token,
      //   account_id: metadata.accounts[0].id
      // });
      console.log('Public Token: ' + public_token);
      switch (metadata.accounts.length) {
        case 0:
          // Select Account is disabled: https://dashboard.plaid.com/link/account-select
          break;
        case 1:
          console.log('Customer-selected account ID: ' + metadata.accounts[0].id);
          break;
        default:
          // Multiple Accounts is enabled: https://dashboard.plaid.com/link/account-select
      }
    },
    onExit: async function(err, metadata) {
      // The user exited the Link flow.
      if (err != null) {
          // The user encountered a Plaid API error
          // prior to exiting.
      }
      // metadata contains information about the institution
      // that the user selected and the most recent
      // API request IDs.
      // Storing this information can be helpful for support.
    },
  };

  var linkHandler = Plaid.create(configs);

  document.getElementById('link-button').onclick = function() {
    linkHandler.open();
  };
})();
</script>