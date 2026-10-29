<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A PMC's staff roster: each person, the properties they cover, and every change made
 * to them (Joe, 2026-10-29). The history is what will later update Assets' contacts.
 */
class CreateStaff extends Migration
{
  public function up()
  {
    $this->forge->addField([
      'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
      'pmc_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
      'name' => ['type' => 'VARCHAR', 'constraint' => 255],
      // One of Config\Partner::$positions.
      'position' => ['type' => 'VARCHAR', 'constraint' => 60],
      'title' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
      'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
      'mobile_phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
      'office_phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
      'start_date' => ['type' => 'DATE', 'null' => true],
      // Set when the person leaves; a row with an end date is "departed", never deleted.
      'end_date' => ['type' => 'DATE', 'null' => true],
      'notes' => ['type' => 'TEXT', 'null' => true],
      'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
      'updated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
      'created_at' => ['type' => 'DATETIME', 'null' => true],
      'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey('id', true);
    $this->forge->addKey(['pmc_id', 'end_date']);
    $this->forge->createTable('staff');

    $this->forge->addField([
      'staff_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
      'property_number' => ['type' => 'VARCHAR', 'constraint' => 20],
      'created_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey(['staff_id', 'property_number'], true);
    $this->forge->addKey('property_number');
    $this->forge->createTable('staff_properties');

    $this->forge->addField([
      'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
      'staff_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
      'pmc_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
      'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
      // added | updated | departed | reinstated
      'action' => ['type' => 'VARCHAR', 'constraint' => 20],
      // JSON: {field: {from, to}} for an update; the whole record for an add.
      'changes' => ['type' => 'TEXT', 'null' => true],
      'created_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey('id', true);
    $this->forge->addKey(['staff_id', 'created_at']);
    $this->forge->addKey(['pmc_id', 'created_at']);
    $this->forge->createTable('staff_changes');
  }
  public function down()
  {
    $this->forge->dropTable('staff_changes', true);
    $this->forge->dropTable('staff_properties', true);
    $this->forge->dropTable('staff', true);
  }
}
