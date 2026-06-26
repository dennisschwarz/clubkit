<?php

declare(strict_types=1);

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Events\Database\Factories\EventFactory;
use Modules\Members\Models\Member;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'starts_at',
        'ends_at',
        'location',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    /**
     * Einmalige Sonder-Assignments: Person ↔ Termin (immer verfügbar).
     * Pivot-Spalte: description (freier Text, was die Person bei diesem Termin tut).
     * Kein Bezug zum Management-Modul — rein event-spezifisch.
     */
    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'event_assignments')
                    ->withPivot('description')
                    ->withTimestamps();
    }

    /**
     * Vereinsfunktionen (nur wenn Management-Modul aktiv ist).
     * Mitglieder der Funktion sind implizit am Termin beteiligt.
     * Aufruf nur nach Prüfung via ModuleLoader::isActive('management').
     */
    public function managementFunctions(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Management\Models\ManagementFunction::class,
            'event_management_function',
            'event_id',
            'management_function_id'
        )->withTimestamps();
    }

    /**
     * Aufgaben (nur wenn Management-Modul aktiv ist).
     * Wer die Aufgabe übernimmt: TBD in zukünftiger Erweiterung.
     * Aufruf nur nach Prüfung via ModuleLoader::isActive('management').
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Management\Models\ManagementTask::class,
            'event_task',
            'event_id',
            'task_id'
        )->withTimestamps();
    }

    /**
     * Zugeordnete Teams (nur wenn Teams-Modul aktiv ist).
     * Aufruf nur nach Prüfung via ModuleLoader::isActive('teams').
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Teams\Models\Team::class,
            'event_team',
            'event_id',
            'team_id'
        )->withTimestamps();
    }

    /**
     * Ersteller des Termins.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
