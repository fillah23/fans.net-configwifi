<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Olt extends Model
{
    use HasFactory;
    protected $fillable = [
        'nama', 'tipe', 'ip', 'port', 'card', 'user', 'pass', 'community_read', 'community_write', 'port_snmp'
    ];
}
