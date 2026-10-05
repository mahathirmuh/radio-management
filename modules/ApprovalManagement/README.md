# Approval Management — Dynamic Approval Engine

Approval yang **data-driven**: sequential *stages*, *parallel* approvers per stage
(rule ALL / ANY / QUORUM), penyisipan approver **saat berjalan** (runtime), dan
**custom field / lampiran** wajib per approver.

## Arsitektur

```
TEMPLATE (dikonfigurasi admin)          RUNTIME (snapshot saat approval dibuat)
approval_flows                          approvals
  └ approval_stages   (urutan + rule)     └ approval_stage_instances
      └ approval_steps (approver)             └ approval_step_instances
          └ approval_step_fields               ├ approval_field_values
             (definisi field custom)           ├ approval_attachments
                                               └ approval_histories
```

Saat approval dibuat, template disalin ke `*_instances`. Semua perubahan runtime
(mis. sisip approver di tengah) terjadi di instance — template & approval lain
tidak terpengaruh.

## Cara pakai (developer)

Tempelkan approval ke **model bisnis apa pun** (polymorphic). Cukup 1 baris saat
data diajukan:

```php
use Modules\ApprovalManagement\Services\ApprovalService;

$pr = PurchaseRequest::create($data);

app(ApprovalService::class)->createApproval($pr, $flowId);
```

Cek status:

```php
$approval = $pr->approval; // jika model punya morphOne, atau query Approval by model_type+model_id
$approval->status;                       // DRAFT | PENDING | APPROVED | REJECTED | REVISED
$approval->currentStageInstance?->name;  // stage aktif
```

Opsional, tambahkan relasi di model bisnis:

```php
public function approval()
{
    return $this->morphOne(\Modules\ApprovalManagement\Models\Approval::class, 'model');
}
```

### API service utama

| Method | Kegunaan |
|---|---|
| `createApproval(Model $model, int $flowId): Approval` | Mulai approval (snapshot flow) |
| `approve($approval, $note, $payload, $user): Approval` | Setujui step user |
| `reject($approval, $note, $payload, $user)` | Tolak (menolak seluruh approval) |
| `revise($approval, $note, $payload, $user)` | Kirim balik untuk revisi |
| `act($approval, 'approve'|'reject'|'revise', $payload, $user)` | Handler inti |
| `insertAdhocStep($approval, $data, $user)` | Sisip approver runtime |
| `delegate($approval, $toUserId, $note, $user)` | Delegasikan step |
| `takeover($approval, $note, $user)` | Ambil alih approval |

`$payload`:
```php
[
    'note'        => 'catatan',
    'fields'      => ['memo' => 'isi', 'priority' => 'High'],
    'attachments' => ['doc' => [
        ['original_name' => 'a.pdf', 'path' => 'approval-attachments/a.pdf', 'mime' => 'application/pdf', 'size' => 1024],
    ]],
]
```
> Di HTTP layer (`ApiApprovalInboxController@act`) file di-upload otomatis ke disk
> `public` dan payload attachments dibangun untukmu.

## Cara pakai (admin & approver — UI)

- **Config flow**: `/approval-management/flows` — buat Flow → expand → Stage
  (pilih rule ALL/ANY/QUORUM) → expand → Step (approver) + **Custom Fields builder**.
- **Inbox approver**: `/approval-management/inbox` — daftar approval menunggu; review,
  isi field/upload, Approve/Reject/Revise; menu *Advanced* untuk sisip approver,
  delegate, atau takeover.
- **Log**: `/approval-management/delegate`, `/approval-management/takeover`.

## Aturan evaluasi stage

- **ALL** — semua step di stage harus `APPROVED`.
- **ANY** — satu `APPROVED` cukup; sisanya jadi `SKIPPED`.
- **QUORUM** — jumlah `APPROVED` ≥ `quorum_count`.
- **REJECT** apa pun langsung menolak seluruh approval (`REJECTED`).

Stage berjalan berurutan (`stage_order`); penyisipan runtime memakai order desimal
(mis. 2.5 di antara 2 dan 3).

## Otorisasi

Hanya yang berhak yang bisa act pada sebuah step: user yang di-assign, pemegang
`role_id` step, delegasi aktif, atau yang melakukan takeover.

## Setup

```bash
php artisan migrate
php artisan storage:link   # agar lampiran approval bisa diakses publik
```
