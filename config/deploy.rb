# config valid only for current version of Capistrano
lock '3.4.0'

#set :application, 'billing'
#set :repo_url, 'repo_url'

# Default branch is :master
ask :branch, `git rev-parse --abbrev-ref HEAD`.chomp

# Default deploy_to directory is /var/www/my_app_name
#set :deploy_to, '/home/ubuntu/billing.nimasoftware.com'

# Default value for :scm is :git
# set :scm, :git

# Default value for :format is :pretty
# set :format, :pretty

# Default value for :log_level is :debug
# set :log_level, :debug

# Default value for :pty is false
# set :pty, true

# Default value for :linked_files is []
# set :linked_files, fetch(:linked_files, []).push('config/database.yml', 'config/secrets.yml')

# Default value for linked_dirs is []
# set :linked_dirs, fetch(:linked_dirs, []).push('log', 'tmp/pids', 'tmp/cache', 'tmp/sockets', 'vendor/bundle', 'public/system')

# Default value for default_env is {}
# set :default_env, { path: "/opt/ruby/bin:$PATH" }

# Default value for keep_releases is 5
# set :keep_releases, 5

set :application, "billing"  # EDIT your app name
set :repo_url,  "git@bitbucket.org:stev_ro/billing-laravel.git" # EDIT your git repository
set :deploy_to, "/home/ubuntu/billing.nimasoftware.com" # EDIT folder where files should be deployed to
 

#set :branch do
#  default_tag = `git tag`.split("\n").last
#
#  tag = Capistrano::CLI.ui.ask "Tag to deploy (make sure to push the tag first): [#{default_tag}] "
#  tag = default_tag if tag.empty?
#  tag
#end

#SSHKit.config.command_map[:composer] = "/usr/local/bin/composer"

namespace :deploy do
     
    desc "Build"
    after :updated, :build do
        on roles(:app) do
            within release_path  do
                #execute :composer, "install --no-dev --quiet" # install dependencies
                #execute :chmod, "u+x artisan" # make artisan executable
            end
        end
    end
 
    desc "Restart"
    task :restart do
        on roles(:app) do
            within release_path  do
                execute :chmod, "-R 777 app/storage/cache"
                execute :chmod, "-R 777 app/storage/logs"
                execute :chmod, "-R 777 app/storage/meta"
                execute :chmod, "-R 777 app/storage/sessions"
                execute :chmod, "-R 777 app/storage/views"
                execute :chown, "ubuntu:www-data .env"
            end
        end
    end
 
end
