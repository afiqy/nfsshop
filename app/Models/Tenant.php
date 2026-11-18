<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
	public static function getCustomColumns(): array
	{
		return [
			'id',
			'account_owner_id',
		];
	}
}
