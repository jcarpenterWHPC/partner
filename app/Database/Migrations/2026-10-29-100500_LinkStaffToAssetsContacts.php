<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A roster entry seeded from Assets remembers which contact it came from, and which
 * properties Assets last gave it, so a later sync adds what is new there without
 * undoing what the PMC changed here.
 */
class LinkStaffToAssetsContacts extends Migration
{
  public function up()
  {
    $this->forge->addColumn('staff', [
      'contact_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'pmc_id'],
      // JSON list of property numbers as of the last sync from Assets.
      'synced_properties' => ['type' => 'TEXT', 'null' => true, 'after' => 'notes'],
      'synced_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'synced_properties'],
    ]);
    $this->forge->addKey('contact_id', false, true);
    $this->forge->processIndexes('staff');
  }
  public function down()
  {
    try
    {
      $this->forge->dropKey('staff', 'staff_contact_id', false);
    }
    catch (\Throwable)
    {
      // SQLite drops the index with the column.
    }
    $this->forge->dropColumn('staff', ['contact_id', 'synced_properties', 'synced_at']);
  }
}
