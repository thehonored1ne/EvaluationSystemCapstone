<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profanities', function (Blueprint $table) {
            $table->id();
            $table->string('word', 191)->unique();
            $table->string('category', 50)->default('custom')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed default words into the table
        $tagalogWords = [
            'putang ina mo', 'putangina mo', 'putang ina', 'putangina', 'putanginamo',
            'tangina mo', 'tanginamo', 'tangina', 'tanginang',
            'pesteng yawa', 'leching yan', 'wala kang hiya', 'walang hiya', 'walanghiya',
            'wala kang kwenta', 'walang kwenta', 'mangmang ka', 'baliw ka', 'lintik ka',
            'pakyu ka', 'pak yu', 'pakyu', 'pakshet', 'pak shet', 'sira ang ulo',
            'sira ulo', 'siraulo',
            'gago', 'gagong', 'gaga', 'tarantado', 'tarantadong',
            'ulol', 'ulolang', 'pucha', 'puchanggala', 'puchangina',
            'kupal', 'kupaling', 'bobo', 'bobong', 'boba',
            'tanga', 'tangang', 'inutil', 'inutilang',
            'lintik', 'lintikan', 'leche', 'letse', 'letseplan',
            'bwisit', 'bwisitin', 'nakakabwisit', 'hayop', 'hayup',
            'hindot', 'hindotan', 'jakol', 'jakulero',
            'kantot', 'kantotin', 'yawa', 'demonyo', 'demonyong',
            'buang', 'buanga', 'gunggong', 'gungong',
            'engot', 'engoting', 'ungas', 'ungasang',
            'baliw', 'baliwang', 'ampota', 'ampotak',
            'salbahe', 'salbaheng', 'hudas', 'hudasang',
            'duwag', 'duwaging', 'sinungaling', 'sinungalingang',
            'burat', 'burating', 'puke', 'puking',
            'pwet', 'pwetan', 'bilat', 'bilating', 'pangit', 'panget',
        ];

        $englishWords = [
            'motherfucker', 'motherfucking', 'son of a bitch', 'bloody hell',
            'fuck', 'fucker', 'fucking', 'fucked', 'fuckup',
            'shit', 'shitty', 'shithead', 'bullshit', 'horseshit', 'shitface', 'shitbag',
            'bitch', 'bitchy', 'bitches',
            'asshole', 'ass', 'arse', 'arsehole',
            'bastard', 'bastards', 'cunt', 'cunts',
            'dick', 'dickhead', 'dickface', 'cock', 'cocksucker',
            'pussy', 'pussies', 'whore', 'whores', 'whorehouse',
            'slut', 'slutty', 'sluts', 'damn', 'dammit', 'damned',
            'hell', 'crap', 'crappy', 'piss', 'pissed', 'pisser',
            'twat', 'twats', 'wanker', 'wankers', 'wank', 'bollocks',
            'bugger', 'prick', 'pricks', 'skank', 'skanky',
            'douchebag', 'douche', 'jackass', 'jackasses',
            'dipshit', 'dumbass', 'dumbfuck', 'numbnuts',
            'fag', 'faggot', 'retard', 'retarded',
            'scumbag', 'scum', 'pervert', 'perv', 'cuck', 'cucked',
        ];

        $now = now();
        $records = [];
        $seen = [];

        foreach ($tagalogWords as $word) {
            $normalized = strtolower(trim($word));
            if (! isset($seen[$normalized])) {
                $seen[$normalized] = true;
                $records[] = [
                    'word' => $normalized,
                    'category' => 'tagalog',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($englishWords as $word) {
            $normalized = strtolower(trim($word));
            if (! isset($seen[$normalized])) {
                $seen[$normalized] = true;
                $records[] = [
                    'word' => $normalized,
                    'category' => 'english',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('profanities')->insert($records);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profanities');
    }
};
