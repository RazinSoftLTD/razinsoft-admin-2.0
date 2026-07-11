<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class TicketAgent extends Model
{
    protected $guarded = [];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function groups(): BelongsToMany { return $this->belongsToMany(TicketGroup::class, 'ticket_agent_group', 'ticket_agent_id', 'ticket_group_id'); }
}
