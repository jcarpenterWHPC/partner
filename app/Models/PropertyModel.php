<?php

namespace App\Models;

use CodeIgniter\Model;

class PropertyModel extends Model
{
  protected $table = 'properties';
  protected $primaryKey = 'property_number';
  protected $returnType = 'array';
  protected $useTimestamps = true;
  protected $useAutoIncrement = false;
  protected $allowedFields = ['property_number', 'name', 'city', 'region', 'units', 'asset_manager', 'management_co', 'pmc_id', 'status', 'is_active', 'synced_at'];
  /** A PMC's current properties, by name. */
  public function forPmc(int $pmcId): array
  {
    return $this->where('pmc_id', $pmcId)->where('is_active', 1)->orderBy('name')->findAll();
  }
}
