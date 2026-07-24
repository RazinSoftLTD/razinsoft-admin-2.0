<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class TicketType extends Model
{
    protected $guarded = [];
    public function agents(): BelongsToMany { return $this->belongsToMany(TicketAgent::class, 'ticket_agent_type', 'ticket_type_id', 'ticket_agent_id'); }
}
