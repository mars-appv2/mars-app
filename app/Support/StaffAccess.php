public static function routersFor($user)
{
    if (!$user || !Schema::hasTable('mikrotiks')) return collect();

    $q = DB::table('mikrotiks');
    $cols = Schema::getColumnListing('mikrotiks');

    // Operator = semua router
    if (self::hasRole($user, ['operator'])) {
        return $q->orderBy('id','desc')->get();
    }

    // 1) PAKAI assigned_user_id (paling cepat & jelas)
    if (in_array('assigned_user_id', $cols)) {
        return $q->where('assigned_user_id', $user->id)->orderBy('id','desc')->get();
    }

    // 2) (opsional) pakai team
    if (in_array('team_id', $cols) && Schema::hasColumn('users','team_id') && isset($user->team_id)) {
        return $q->where('team_id', $user->team_id)->orderBy('id','desc')->get();
    }

    // 3) Fallback: mapping tabel staff_mikrotik kalau ada
    if (Schema::hasTable('staff_mikrotik')) {
        $sm = Schema::getColumnListing('staff_mikrotik');
        $uCol = in_array('user_id',$sm) ? 'user_id' : (in_array('staff_id',$sm) ? 'staff_id' : null);
        $mCol = in_array('mikrotik_id',$sm) ? 'mikrotik_id' : (in_array('device_id',$sm) ? 'device_id' : null);
        if ($uCol && $mCol) {
            $ids = DB::table('staff_mikrotik')->where($uCol, $user->id)->pluck($mCol);
            if ($ids->count()) return $q->whereIn('id', $ids)->orderBy('id','desc')->get();
        }
    }

    return collect(); // default: teknisi tanpa assignment tidak lihat router
}
