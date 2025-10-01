<?php
namespace App\Services;

use App\Models\Mikrotik;
use RouterOS\Client;
use RouterOS\Query;

class RouterOSService
{
    protected $client;
    protected $m;

    public function __construct(Mikrotik $m)
    {
        $this->m = $m;
        $this->client = new Client([
            'host'     => $m->host,
            'user'     => $m->username,
            'pass'     => $m->password,
            'port'     => $m->port ?: 8728,
            'timeout'  => 8,
            'attempts' => 1,
        ]);
        \Log::info('[ROS] connected', ['host'=>$m->host,'port'=>$m->port,'user'=>$m->username]);
    }

    /** Execute query; deteksi trap; selalu return array */
    protected function run(Query $q): array
    {
        try {
            $resp = $this->client->query($q)->read();
            // Trap / error dari ROS biasanya muncul sbg array dgn key 'message'/'category'
            if (!empty($resp) && isset($resp[0]) && is_array($resp[0]) && (isset($resp[0]['message']) || isset($resp[0]['category']))) {
                \Log::error('[ROS_ERR] trap', ['path'=>$this->pathOf($q), 'resp'=>$resp]);
                return [];
            }
            return $resp ?? [];
        } catch (\Throwable $e) {
            \Log::error('[ROS_ERR] exception', ['path'=>$this->pathOf($q), 'err'=>$e->getMessage()]);
            return [];
        }
    }

    private function pathOf(Query $q): string
    {
        try {
            $ref  = new \ReflectionClass($q);
            $prop = $ref->getProperty('query');
            $prop->setAccessible(true);
            return (string)$prop->getValue($q);
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /* ================== INTERFACE ================== */
    public function interfaces(): array
    {
        \Log::info('[ROS_CMD] /interface/print');
        return $this->run(new Query('/interface/print'));
    }

    public function monitorInterface(string $iface): array
    {
        try {
            $q = (new Query('/interface/monitor-traffic'))
                ->equal('interface', $iface)
                ->equal('once', '');
            $r   = $this->client->query($q)->read();
            $row = $r[0] ?? [];
            $rx  = isset($row['rx-bits-per-second']) ? (int)$row['rx-bits-per-second'] : 0;
            $tx  = isset($row['tx-bits-per-second']) ? (int)$row['tx-bits-per-second'] : 0;
            return ['rx'=>$rx,'tx'=>$tx];
        } catch (\Throwable $e) {
            \Log::error('[ROS_ERR] monitor', ['iface'=>$iface, 'err'=>$e->getMessage()]);
            return ['rx'=>0,'tx'=>0];
        }
    }

    public function addVlan(string $name, string $iface, int $vid): void
    {
        \Log::info('[ROS_CMD] vlan.add', compact('name','iface','vid'));
        $this->run(
            (new Query('/interface/vlan/add'))
                ->equal('name', $name)
                ->equal('interface', $iface)
                ->equal('vlan-id', $vid)
        );
    }

    public function addBridge(string $name): void
    {
        \Log::info('[ROS_CMD] bridge.add', compact('name'));
        $this->run((new Query('/interface/bridge/add'))->equal('name', $name));
    }

    public function addBridgePort(string $bridge, string $iface): void
    {
        \Log::info('[ROS_CMD] bridge.port.add', compact('bridge','iface'));
        $this->run(
            (new Query('/interface/bridge/port/add'))
                ->equal('bridge', $bridge)
                ->equal('interface', $iface)
        );
    }

    /* ================== IP ADDRESS (ROS v6/7) ================== */
    public function ipAddressAdd(string $address, string $interface, string $comment = ''): void
    {
        $payload = ['address' => $address, 'interface' => $interface];
        if ($comment !== '') $payload['comment'] = $comment;

        \Log::info('[ROS_CMD] /ip/address/add', $payload);

        $q = new Query('/ip/address/add');
        foreach ($payload as $k => $v) {
            $q->equal($k, $v);
        }
        $this->run($q);
    }

    public function ipAddressRemove(string $address): void
    {
        $id = $this->findId('/ip/address', ['?address' => $address]); // helper otomatis tambah '/print'
        if ($id) {
            \Log::info('[ROS_CMD] /ip/address/remove', ['.id'=>$id,'address'=>$address]);
            $this->run((new Query('/ip/address/remove'))->equal('.id', $id));
        } else {
            \Log::info('[ROS_CMD] /ip/address/remove.skip', ['address'=>$address,'reason'=>'not_found']);
        }
    }

    /**
     * Helper cari .id via *print*
     * $matcher: boleh pakai '?prop' => 'val' (where) atau 'prop' => 'val' (equal)
     */
    protected function findId(string $path, array $matcher): ?string
    {
        if (substr($path, -6) !== '/print') $path .= '/print';
        $q = new Query($path);
        foreach ($matcher as $k => $v) {
            if (strlen($k) > 0 && $k[0] === '?') {
                $q->where(substr($k,1), $v);
            } else {
                $q->equal($k, $v);
            }
        }
        $rows = $this->run($q);
        if (is_array($rows) && isset($rows[0])) {
            if (isset($rows[0]['.id'])) return $rows[0]['.id'];
            if (isset($rows[0]['id']))  return $rows[0]['id'];
        }
        return null;
    }

    /* ================== PPPoE ================== */
    public function pppSecrets(): array  { return $this->run(new Query('/ppp/secret/print')); }
    public function pppActive(): array   { return $this->run(new Query('/ppp/active/print')); }
    public function pppProfiles(): array { return $this->run(new Query('/ppp/profile/print')); }

    /** Tambah secret PPPoE (service=pppoe, disabled=no) */
    public function pppAdd(string $name, string $password, string $profile='default', string $comment=''): void
    {
        \Log::info('[ROS_CMD] ppp.add', compact('name','profile'));
        $q = (new Query('/ppp/secret/add'))
            ->equal('name', $name)
            ->equal('password', $password)
            ->equal('service', 'pppoe')
            ->equal('disabled', 'no');
        if ($profile !== '') $q->equal('profile', $profile);
        if ($comment !== '') $q->equal('comment', $comment);
        $this->run($q);

        $id = $this->findId('/ppp/secret', ['?name'=>$name]);
        if (!$id) \Log::error('[ROS_ERR] ppp.add.not_created', ['name'=>$name, 'profile'=>$profile]);
        else      \Log::info('[ROS_CMD] ppp.add.ok', ['name'=>$name, 'id'=>$id]);
    }

    /** Update secret PPP (password/profile/disabled) */
    public function pppSet(string $name, array $attrs): void
    {
        \Log::info('[ROS_CMD] ppp.set', ['name'=>$name,'attrs'=>$attrs]);
        $id = $this->findId('/ppp/secret', ['?name'=>$name]);
        if (!$id) { \Log::error('[ROS_ERR] ppp.set.not_found', ['name'=>$name]); return; }

        if (array_key_exists('disabled', $attrs)) {
            $v = $attrs['disabled'];
            if ($v === true || $v === 1 || $v === '1') $attrs['disabled'] = 'yes';
            elseif ($v === false || $v === 0 || $v === '0' || $v === '') $attrs['disabled'] = 'no';
        }

        $q = (new Query('/ppp/secret/set'))->equal('.id', $id);
        foreach ($attrs as $k=>$v) { if ($v !== null) $q->equal($k, $v); }
        $this->run($q);
    }

    public function pppRemove(string $name): void
    {
        \Log::info('[ROS_CMD] ppp.remove', compact('name'));
        $id = $this->findId('/ppp/secret', ['?name'=>$name]);
        if ($id) $this->run((new Query('/ppp/secret/remove'))->equal('.id', $id));
        else \Log::error('[ROS_ERR] ppp.remove.not_found', ['name'=>$name]);
    }

    /** Tambah profil PPP (opsional rate-limit "10M/10M") */
    public function pppProfileAdd(string $name, ?string $rateLimit=null, ?string $localAddr=null, ?string $remotePool=null): void
    {
        \Log::info('[ROS_CMD] ppp.profile.add', compact('name','rateLimit'));
        $q = (new Query('/ppp/profile/add'))->equal('name', $name);
        if ($rateLimit)  $q->equal('rate-limit', $rateLimit);
        if ($localAddr)  $q->equal('local-address', $localAddr);
        if ($remotePool) $q->equal('remote-address', $remotePool);
        $this->run($q);
    }
}
