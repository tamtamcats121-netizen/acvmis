<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentApprovalHistory extends Model
{
    protected $fillable = [
        'student_id',
        'admin_id',
        'coach_id',
        'decision',
        'remarks',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
