<?php

class EntityModel extends Eloquent
{
	protected $softDelete = true;
	protected $hidden = array('id', 'created_at', 'updated_at', 'deleted_at');

	public static function createNew()
	{
		$className = get_called_class();
		$entity = new $className();
		$entity->account_id = Auth::user()->account_id;
		
		$lastEntity = $className::scope()->orderBy('public_id', 'DESC')->first();

		if ($lastEntity)
		{
			$entity->public_id = $lastEntity->public_id + 1;
		}
		else
		{
			$entity->public_id = 1;
		}

		return $entity;
	}

	public static function getPrivateId($publicId)
	{
		$className = get_called_class();
		return $className::scope($publicId)->pluck('id');
	}

	public function getNmae()
	{
		return '';
	}

	public function scopeScope($query, $publicId = false)
	{
		$query->whereAccountId(Auth::user()->account_id);

		if ($publicId)
		{
			if (is_array($publicId))
			{
				$query->whereIn('public_id', $publicId);
			}
			else
			{
				$query->wherePublicId($publicId);
			}
		}
		
		return $query;
	}
}