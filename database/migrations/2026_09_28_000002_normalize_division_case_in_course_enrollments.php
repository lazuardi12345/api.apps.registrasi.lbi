<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalise the 'division' column in course_enrollments to consistent
     * lowercase values: 'ppb', 'ppp', 'bipa'.
     *
     * Before this migration the legacy mapper could produce uppercase ('PPP',
     * 'BIPA', 'PPB') or mixed variants ('ppb_english').  This is a pure data
     * migration — no schema change required.
     */
    public function up(): void
    {
        // Uppercase → lowercase
        DB::table('course_enrollments')->where('division', 'PPP')->update(['division' => 'ppp']);
        DB::table('course_enrollments')->where('division', 'BIPA')->update(['division' => 'bipa']);
        DB::table('course_enrollments')->where('division', 'PPB')->update(['division' => 'ppb']);

        // Variant spellings → canonical
        DB::table('course_enrollments')->where('division', 'ppb_english')->update(['division' => 'ppb']);
        DB::table('course_enrollments')->where('division', 'bipa')->update(['division' => 'bipa']); // idempotent
        DB::table('course_enrollments')->where('division', 'ppp')->update(['division' => 'ppp']);   // idempotent
    }

    public function down(): void
    {
        // Intentionally left empty — reversing a normalisation is not safe
        // because we cannot know which rows were 'PPP' vs 'ppp' originally.
    }
};
