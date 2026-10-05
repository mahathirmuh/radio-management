<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\UserManagement;
 
use Illuminate\Database\Eloquent\Collection;  
use Illuminate\Database\Eloquent\Model; 

/**
 * Class UserMdka
 * 
 * @property string $email 
 * @property string $verify_key  
 *
 * @package App\Models
 */
class UserMdka extends Model
{ 
	protected $connection = 'mdka';

	protected $table = 'users';

	protected $casts = [
		'verify_key' => 'string', 
	];

	protected $hidden = [
		'password',
		'remember_token', 
	]; 

}
