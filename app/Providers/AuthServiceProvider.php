<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Only the token endpoint. The personal-access-token and client
        // routes let any logged-in staff mint a year-long API token
        // (Sarah, 2026-11-16).
        Passport::routes(function ($router) {
            $router->forAccessTokens();
        });

        Gate::before(function ($user, $ability) {
            if (in_array($ability, ['backup', 'superadmin', 
                'manage_modules'])) {
                $administrator_list = config('constants.administrator_usernames');
            
                if (in_array(strtolower($user->username), explode(',', strtolower($administrator_list)))) {
                    return true;
                }
            } else {
                if ($user->hasRole('Admin#' . $user->business_id)) {
                    return true;
                }
            }
        });
    }
}
