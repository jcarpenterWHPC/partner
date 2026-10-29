<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
  public function up()
  {
    $this->forge->addField([
      'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
      'email' => ['type' => 'VARCHAR', 'constraint' => 255],
      'name' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
      // The property management company the person works for; null for a WHPC admin.
      'pmc_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
      'is_admin' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
      'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
      'last_login_at' => ['type' => 'DATETIME', 'null' => true],
      'created_at' => ['type' => 'DATETIME', 'null' => true],
      'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey('id', true);
    $this->forge->addUniqueKey('email');
    $this->forge->addKey('pmc_id');
    $this->forge->createTable('users');
  }
  public function down()
  {
    $this->forge->dropTable('users', true);
  }
}
