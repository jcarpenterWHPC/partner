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
  /** A management company's current properties, by name. */
  public function forPmc(int $pmcId): array
  {
    return $this->where('pmc_id', $pmcId)->where('is_active', 1)->orderBy('name')->findAll();
  }
  /** Every current property, by name: what a service agency may be assigned to. */
  public function allActive(): array
  {
    return $this->where('is_active', 1)->orderBy('name')->findAll();
  }
  /** The properties an organisation's people may cover. */
  public function forOrg(array $org): array
  {
    return PmcModel::isServices($org) ? $this->allActive() : $this->forPmc((int)$org['id']);
  }
}
