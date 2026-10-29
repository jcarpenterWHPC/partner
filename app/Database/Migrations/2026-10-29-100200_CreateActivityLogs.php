<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogs extends Migration
{
  public function up()
  {
    $this->forge->addField([
      'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
      'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
      'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
      'action' => ['type' => 'VARCHAR', 'constraint' => 64],
      'detail' => ['type' => 'TEXT', 'null' => true],
      'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
      'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
      'created_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey('id', true);
    $this->forge->addKey(['user_id', 'created_at']);
    $this->forge->addKey('action');
    $this->forge->createTable('activity_logs');
  }
  public function down()
  {
    $this->forge->dropTable('activity_logs', true);
  }
}
