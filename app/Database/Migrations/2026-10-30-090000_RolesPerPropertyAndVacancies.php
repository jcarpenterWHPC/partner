<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The portal takes Assets' shape: a person's role belongs to each property assignment,
 * not to the person (Assets' asset_contact_roles is property × role × contact). The
 * person keeps a main position as the default for new assignments.
 *
 * Organisations get a kind: a management company owns properties; a service agency
 * (LSS, Meridian Resident Services) staffs other companies' properties.
 *
 * Vacancies: a property × role with nobody in it, since when, and who is covering.
 *
 * A person's email may be recorded as not applicable rather than merely blank.
 */
class RolesPerPropertyAndVacancies extends Migration
{
  public function up()
  {
    $this->forge->addColumn('pmcs', [
      'kind' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'management', 'after' => 'name'],
    ]);
    $this->forge->addColumn('staff', [
      'email_na' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'email'],
    ]);
    $this->forge->addColumn('staff_properties', [
      'role' => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => '', 'after' => 'property_number'],
    ]);
    $sp = $this->db->prefixTable('staff_properties');
    $st = $this->db->prefixTable('staff');
    $this->db->query("UPDATE {$sp} SET role = (SELECT position FROM {$st} WHERE {$st}.id = {$sp}.staff_id) WHERE role = ''");

    $this->forge->addField([
      'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
      'pmc_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
      'property_number' => ['type' => 'VARCHAR', 'constraint' => 20],
      'role' => ['type' => 'VARCHAR', 'constraint' => 60],
      'open_since' => ['type' => 'DATE', 'null' => true],
      // Who is looking after the position in the meantime.
      'coverage' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
      // Set when someone is assigned the role again; an open vacancy has none.
      'filled_at' => ['type' => 'DATETIME', 'null' => true],
      'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
      'updated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
      'created_at' => ['type' => 'DATETIME', 'null' => true],
      'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addKey('id', true);
    $this->forge->addKey(['pmc_id', 'filled_at']);
    $this->forge->addKey(['property_number', 'role']);
    $this->forge->createTable('vacancies');
  }
  public function down()
  {
    $this->forge->dropTable('vacancies', true);
    $this->forge->dropColumn('staff_properties', 'role');
    $this->forge->dropColumn('staff', 'email_na');
    $this->forge->dropColumn('pmcs', 'kind');
  }
}
