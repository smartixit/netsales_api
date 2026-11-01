<?php

use App\Enums\RoleKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create network_users table
        Schema::create('network_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('network_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->string('role_scope', 20)->storedAs("'NETWORK'");
            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'INVITED'])->default('ACTIVE');
            $table->unsignedBigInteger('last_event_seq')->default(0);
            $table->timestamps();

            $table->unique(['network_id', 'user_id'], 'uq_network_user');
            $table->unique(['network_id', 'user_id', 'last_event_seq'], 'uq_network_user_seq');

            $table->foreign('network_id')->references('network_id')->on('networks')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign(['role_id', 'role_scope'])->references(['role_id', 'scope'])->on('roles')->onDelete('restrict');
        });

        // 2. Create pos_users table
        Schema::create('pos_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->string('role_scope', 20)->storedAs("'POS'");
            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'INVITED'])->default('ACTIVE');
            $table->unsignedBigInteger('last_event_seq')->default(0);
            $table->timestamps();

            $table->unique(['pos_id', 'user_id'], 'uq_pos_user');
            $table->unique(['pos_id', 'user_id', 'last_event_seq'], 'uq_pos_user_seq');

            $table->foreign('pos_id')->references('pos_id')->on('pos')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign(['role_id', 'role_scope'])->references(['role_id', 'scope'])->on('roles')->onDelete('restrict');
        });

        // 3. Migrate existing Network owners into network_users
        $netOwnerRole = DB::table('roles')->where('role_key', RoleKey::NETWORK_OWNER->value)->first();
        if ($netOwnerRole) {
            $networks = DB::table('networks')->select('network_id', 'owner_id', 'created_at', 'updated_at')->get();
            foreach ($networks as $net) {
                if ($net->owner_id) {
                    DB::table('network_users')->updateOrInsert(
                        ['network_id' => $net->network_id, 'user_id' => $net->owner_id],
                        [
                            'role_id' => $netOwnerRole->role_id,
                            'status' => 'ACTIVE',
                            'last_event_seq' => 1,
                            'created_at' => $net->created_at ?? now('Asia/Aden'),
                            'updated_at' => $net->updated_at ?? now('Asia/Aden'),
                        ]
                    );
                }
            }
        }

        // 4. Migrate existing POS owners into pos_users
        $posAgentRole = DB::table('roles')->where('role_key', RoleKey::POS_AGENT->value)->first();
        if ($posAgentRole) {
            $poses = DB::table('pos')->select('pos_id', 'owner_id', 'created_at', 'updated_at')->get();
            foreach ($poses as $p) {
                if ($p->owner_id) {
                    DB::table('pos_users')->updateOrInsert(
                        ['pos_id' => $p->pos_id, 'user_id' => $p->owner_id],
                        [
                            'role_id' => $posAgentRole->role_id,
                            'status' => 'ACTIVE',
                            'last_event_seq' => 1,
                            'created_at' => $p->created_at ?? now('Asia/Aden'),
                            'updated_at' => $p->updated_at ?? now('Asia/Aden'),
                        ]
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_users');
        Schema::dropIfExists('network_users');
    }
};
