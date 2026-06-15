<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'score_sheet_id',
        'serial_no',
        'candidate_name',
        'p1',
        'p2',
        'p3',
        'p4',
        'average',
        'grade',
    ];

    protected $casts = [
        'p1'      => 'float',
        'p2'      => 'float',
        'p3'      => 'float',
        'p4'      => 'float',
        'average' => 'float',
    ];

    public function scoreSheet()
    {
        return $this->belongsTo(ScoreSheet::class);
    }
}