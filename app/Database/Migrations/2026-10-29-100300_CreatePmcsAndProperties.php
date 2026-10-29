<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The management companies and their properties, synced from the intranet's `assets`
 * table (`partner:sync-properties`). A PMC is named exactly as Assets' management_co.
 */
class CreatePmcsAndProperties extends Migration
{
  public function up()
  {
    $this->forge->addField([
      'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
      'name' => ['type' => 'VARCHAR', 'constraint' => 255],
      'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
      'created_at' => ['type' => 'DATETIME', 'null' => true],
      'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey('id', true);
    $this->forge->addUniqueKey('name');
    $this->forge->createTable('pmcs');

    $this->forge->addField([
      'property_number' => ['type' => 'VARCHAR', 'constraint' => 20],
      'name' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
      'city' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
      'region' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
      'units' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
      'asset_manager' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
      'management_co' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
      'pmc_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
      'status' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
      // 0 once a sync no longer lists the property; its roster links are kept.
      'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
      'synced_at' => ['type' => 'DATETIME', 'null' => true],
      'created_at' => ['type' => 'DATETIME', 'null' => true],
      'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey('property_number', true);
    $this->forge->addKey('pmc_id');
    $this->forge->createTable('properties');
  }
  public function down()
  {
    $this->forge->dropTable('properties', true);
    $this->forge->dropTable('pmcs', true);
  }
}
