<div class="col-md-6 col-md-offset-3">
    <form action="/migration/account" method="post">
        {{ csrf_field() }}

        <div class="form-group">
            <label for="email">E-mail address</label>
            <input type="email" class="form-control" name="email">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" class="form-control" name="password">
        </div>

        <div class="form-group">
            <label for="x_api_secret">X-API-SECRET</label>
            <input type="password" class="form-control" name="x_api_secret">
        </div>

        <div class="form-group">
            <label for="self_hosted_url">Self-hosted url</label>
            <input type="text" class="form-control" name="self_hosted_url" placeholder="With http:// or https://">
        </div>

        <div class="form-group text-center">
            <button class="btn btn-primary">Next step</button>
        </div>
    </form>
</div>