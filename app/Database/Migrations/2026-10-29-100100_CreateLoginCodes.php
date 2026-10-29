<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Digests only — see App\Libraries\LoginCode. */
class CreateLoginCodes extends Migration
{
  public function up()
  {
    $this->forge->addField([
      'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
      'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
      'code_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
      'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
      'attempts' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
      'expires_at' => ['type' => 'DATETIME'],
      'consumed_at' => ['type' => 'DATETIME', 'null' => true],
      'created_at' => ['type' => 'DATETIME'],
      'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
    ]);
    $this->forge->addKey('id', true);
    $this->forge->addKey(['user_id', 'consumed_at']);
    $this->forge->addKey('token_hash');
    $this->forge->createTable('login_codes');
  }
  public function down()
  {
    $this->forge->dropTable('login_codes', true);
  }
}
