<?php

namespace App\Providers;

use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\OtpRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\SecurityBanRepositoryInterface;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserSessionRepositoryInterface;
use App\Repositories\NetworkPosRepository;
use App\Repositories\NetworkRepository;
use App\Repositories\OtpRepository;
use App\Repositories\PosRepository;
use App\Repositories\SecurityBanRepository;
use App\Repositories\UserDeviceRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<string, string>
     */
    public array $bindings = [
        // Phase 1: Auth & Security Core
        UserRepositoryInterface::class => UserRepository::class,
        UserDeviceRepositoryInterface::class => UserDeviceRepository::class,
        UserSessionRepositoryInterface::class => UserSessionRepository::class,
        SecurityBanRepositoryInterface::class => SecurityBanRepository::class,
        OtpRepositoryInterface::class => OtpRepository::class,
        NetworkRepositoryInterface::class => NetworkRepository::class,
        PosRepositoryInterface::class => PosRepository::class,
        NetworkPosRepositoryInterface::class => NetworkPosRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }

    public function boot(): void {}
}
