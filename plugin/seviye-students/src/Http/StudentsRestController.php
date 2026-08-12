<?php

declare(strict_types=1);

namespace Seviye\Students\Http;

use InvalidArgumentException;
use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Events\Event;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Core\Rbac\Role;
use Seviye\Students\Domain\EducationYear;
use Seviye\Students\Domain\ParentRelationship;
use Seviye\Students\Domain\Student;
use Seviye\Students\Domain\StudentStatus;
use Seviye\Students\Rbac\StudentCapability;
use Seviye\Students\Repository\StudentParentRepositoryInterface;
use Seviye\Students\Repository\StudentRepositoryInterface;
use Seviye\Students\Support\StudentImportParser;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/students/*. "Manage" access is scoped at request time, not by
 * capability alone: a user with a branch membership (Şube Müdürü) only ever
 * sees/writes their own branch's students; a user without one (Genel
 * Merkez, Bölge Müdürü) sees/writes every branch. See {@see StudentCapability}.
 */
final class StudentsRestController extends AbstractRestController
{
    public function __construct(
        private readonly StudentRepositoryInterface $students,
        private readonly StudentParentRepositoryInterface $studentParents,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly BranchLookupInterface $branchLookup,
        private readonly StudentImportParser $importParser,
        private readonly EventBusInterface $eventBus
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/students', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'canViewStudents'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(StudentCapability::MANAGE_STUDENTS->value),
                'args' => $this->writableArgs(),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/import', [
            'methods' => 'POST',
            'callback' => [$this, 'import'],
            'permission_callback' => $this->requireCapability(StudentCapability::MANAGE_STUDENTS->value),
            'args' => [
                'csv' => ['required' => true, 'type' => 'string'],
                'branch_id' => ['required' => false, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/promote', [
            'methods' => 'POST',
            'callback' => [$this, 'promote'],
            'permission_callback' => $this->requireCapability(StudentCapability::MANAGE_STUDENTS->value),
            'args' => [
                'from_education_year' => ['required' => true, 'type' => 'string'],
                'class_name_map' => ['required' => false, 'type' => 'object'],
                'branch_id' => ['required' => false, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => $this->requireCapability(StudentCapability::VIEW_OWN_CHILDREN->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'canAccessStudentReadOnly'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canAccessStudent'],
                'args' => $this->writableArgs(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'destroy'],
                'permission_callback' => [$this, 'canAccessStudent'],
            ],
        ]);

        // "Öğrenci profiline fotoğraf/avatar yükleme" - canAccessStudent()'ın
        // AKSİNE, MANAGE_STUDENTS (personel-only) İSTEMİYOR: bir velinin
        // KENDİ çocuğunun fotoğrafını yükleyebilmesi gerekiyor - bkz.
        // canManageStudentPhoto() (personel VEYA o öğrencinin bağlı bir
        // velisi).
        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/(?P<id>\d+)/photo', [
            'methods' => 'PUT',
            'callback' => [$this, 'updatePhoto'],
            'permission_callback' => [$this, 'canManageStudentPhoto'],
            'args' => [
                'attachment_id' => ['required' => false, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/(?P<id>\d+)/parents', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listParents'],
                'permission_callback' => [$this, 'canAccessStudentReadOnly'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'linkParent'],
                'permission_callback' => [$this, 'canAccessStudent'],
                'args' => [
                    'parent_user_id' => ['required' => true, 'type' => 'integer'],
                    'relationship' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/(?P<id>\d+)/parents/(?P<parent_user_id>\d+)', [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'unlinkParent'],
                'permission_callback' => [$this, 'canAccessStudent'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateParent'],
                'permission_callback' => [$this, 'canAccessStudent'],
                'args' => [
                    'display_name' => ['required' => true, 'type' => 'string'],
                    'email' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);
    }

    public function index(): WP_REST_Response
    {
        $branchId = $this->currentUserBranchId();
        $students = $branchId !== null ? $this->students->findByBranch($branchId) : $this->students->all();

        return new WP_REST_Response(array_map($this->serialize(...), $students));
    }

    public function mine(): WP_REST_Response
    {
        $studentIds = $this->studentParents->studentIdsForParent(get_current_user_id());
        $students = array_filter(array_map($this->students->find(...), $studentIds));

        return new WP_REST_Response(array_map($this->serialize(...), array_values($students)));
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $student = $this->students->find((int) $request->get_param('id'));

        if ($student === null) {
            return new WP_REST_Response(['message' => __('Öğrenci bulunamadı.', 'seviye-students')], 404);
        }

        return new WP_REST_Response($this->serialize($student));
    }

    public function canAccessStudent(WP_REST_Request $request): bool
    {
        if (!current_user_can(StudentCapability::MANAGE_STUDENTS->value)) {
            return false;
        }

        $branchId = $this->currentUserBranchId();

        if ($branchId === null) {
            return true;
        }

        $student = $this->students->find((int) $request->get_param('id'));

        return $student !== null && $student->branchId === $branchId;
    }

    /** MANAGE_STUDENTS or read-only VIEW_STUDENTS (Rehberlik). */
    public function canViewStudents(): bool
    {
        return current_user_can(StudentCapability::MANAGE_STUDENTS->value)
            || current_user_can(StudentCapability::VIEW_STUDENTS->value);
    }

    /**
     * Same branch-scoping as canAccessStudent(), but also lets a
     * VIEW_STUDENTS-only user (Rehberlik) read a single student/its
     * parent list - never used for a write route.
     */
    public function canAccessStudentReadOnly(WP_REST_Request $request): bool
    {
        if (!$this->canViewStudents()) {
            return false;
        }

        $branchId = $this->currentUserBranchId();

        if ($branchId === null) {
            return true;
        }

        $student = $this->students->find((int) $request->get_param('id'));

        return $student !== null && $student->branchId === $branchId;
    }

    /**
     * Personel (canAccessStudent()'ın AYNI şube-kapsamlı MANAGE_STUDENTS
     * mantığı) VEYA öğrencinin bağlı bir velisi (scp_student_parents'ın
     * kendisi, StudentGuardianCheckInterface'in yayınlanan Contract'ı
     * DEĞİL - bu, Students modülünün İÇİNDE, kendi
     * StudentParentRepositoryInterface'ine erişimi var, dışarıdan bir
     * Contract'a ihtiyacı yok, o yalnızca DİĞER modüller için).
     */
    public function canManageStudentPhoto(WP_REST_Request $request): bool
    {
        $studentId = (int) $request->get_param('id');

        if (in_array(get_current_user_id(), $this->studentParents->parentUserIdsForStudent($studentId), true)) {
            return true;
        }

        return $this->canAccessStudent($request);
    }

    public function updatePhoto(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $student = $this->students->find($id);

        if ($student === null) {
            return new WP_REST_Response(['message' => __('Öğrenci bulunamadı.', 'seviye-students')], 404);
        }

        $attachmentId = $request->get_param('attachment_id');
        $attachmentId = $attachmentId !== null && $attachmentId !== '' ? (int) $attachmentId : null;

        if ($attachmentId !== null && get_post_type($attachmentId) !== 'attachment') {
            return new WP_REST_Response(['message' => __('Geçersiz görsel.', 'seviye-students')], 422);
        }

        $this->students->updatePhoto($id, $attachmentId);

        $updated = $this->students->find($id);

        return new WP_REST_Response($this->serialize($updated ?? $student));
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $branchId = $this->resolveBranchIdForWrite($request);

        if ($branchId === null || !$this->branchLookup->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Geçersiz şube.', 'seviye-students')], 422);
        }

        try {
            $student = $this->students->create(
                $branchId,
                (string) $request->get_param('first_name'),
                (string) $request->get_param('last_name'),
                EducationYear::fromString((string) $request->get_param('education_year')),
                (string) $request->get_param('class_name'),
                $this->resolveStudentTcNo($request)
            );
        } catch (InvalidArgumentException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 422);
        }

        $response = $this->serialize($student);
        $parentResult = $this->maybeCreateAndLinkParent($student, $request);

        if ($parentResult['error'] !== null) {
            $response['parent_error'] = $parentResult['error'];
        }

        if ($parentResult['credentials'] !== null) {
            $response['parent_credentials'] = $parentResult['credentials'];
        }

        if ($parentResult['linked_existing'] !== null) {
            $response['parent_linked_existing'] = $parentResult['linked_existing'];
        }

        return new WP_REST_Response($response, 201);
    }

    /**
     * "Toplu öğrenci kaydı" - okul yılı başında elle tek tek form doldurmak
     * yerine bir CSV dosyasıyla çok sayıda öğrenciyi tek istekte açar.
     * store()'un aksine veli bilgisi işlemiyor (bkz. maybeCreateAndLinkParent()) -
     * bir CSV satırında hem öğrenci hem veli bilgisi karıştırmak biçimi
     * karmaşıklaştırırdı; veli bağlama ayrı, tekil bir işlem olarak kalıyor
     * (bkz. linkParent()). Hedef şube TÜM dosya için TEK - store()'daki
     * resolveBranchIdForWrite() ile aynı kural (Şube Müdürü her zaman kendi
     * şubesine yazar, HQ branch_id vermek zorunda) - bir dosyada birden
     * fazla şubeye dağılmış satır desteklenmiyor, gerekirse şube başına
     * ayrı bir dosya yüklenir.
     *
     * Kısmi başarı normaldir: bir satırdaki hata diğer satırların
     * içe aktarılmasını engellemez - her satır kendi başarı/hatasıyla
     * ayrı ayrı raporlanır (bkz. serialize edilmiş yanıtın `errors` alanı).
     */
    public function import(WP_REST_Request $request): WP_REST_Response
    {
        $branchId = $this->resolveBranchIdForWrite($request);

        if ($branchId === null || !$this->branchLookup->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Geçersiz şube.', 'seviye-students')], 422);
        }

        $rows = $this->importParser->parse((string) $request->get_param('csv'));

        if ($rows === []) {
            return new WP_REST_Response(['message' => __('CSV içeriği boş veya okunamadı.', 'seviye-students')], 422);
        }

        $imported = [];
        $errors = [];

        foreach ($rows as $row) {
            if ($row->error !== null) {
                $errors[] = ['line' => $row->lineNumber, 'message' => $row->error];
                continue;
            }

            try {
                $student = $this->students->create(
                    $branchId,
                    $row->firstName,
                    $row->lastName,
                    EducationYear::fromString($row->educationYear),
                    $row->className,
                    $this->validateTcNo($row->tcNo)
                );
                $imported[] = $this->serialize($student);
            } catch (InvalidArgumentException $exception) {
                $errors[] = ['line' => $row->lineNumber, 'message' => $exception->getMessage()];
            }
        }

        return new WP_REST_Response([
            'imported_count' => count($imported),
            'error_count' => count($errors),
            'imported' => $imported,
            'errors' => $errors,
        ]);
    }

    /**
     * "Toplu sınıf/eğitim yılı geçişi" - CSV import öğrenci OLUŞTURMAyı
     * çözdü, bu ise MEVCUT öğrencileri her yıl sonunda toplu olarak bir
     * sonraki eğitim yılına taşır (bkz. Domain\EducationYear::next()).
     * Yalnızca ACTIVE öğrenciler ve from_education_year'i eşleşenler
     * etkilenir - mezun/pasif öğrenciler asla dokunulmaz. class_name_map
     * (opsiyonel) eski sınıf adını yeniye çevirir (ör. "5-A" -> "6-A");
     * haritada karşılığı olmayan bir sınıf adı DEĞİŞMEDEN kalır - okulun
     * sınıf adlandırma biçimini tahmin etmek yerine açıkça belirtilmesini
     * ister (bkz. normalizeClassNameMap()).
     *
     * branch_id null bırakılırsa - store()/import()'un aksine - HQ için
     * geçersiz bir istek değil, tam tersine "her şubeyi kapsa" anlamına
     * gelir (resolveBranchIdForWrite() burada KULLANILMAZ, o yalnızca "kendi
     * şubem YOKSA istekteki şube" der, "yoksa hepsi" demez).
     */
    public function promote(WP_REST_Request $request): WP_REST_Response
    {
        $fromRaw = (string) $request->get_param('from_education_year');

        if (!EducationYear::isValid($fromRaw)) {
            return new WP_REST_Response(['message' => __('Geçersiz eğitim yılı.', 'seviye-students')], 422);
        }

        $from = EducationYear::fromString($fromRaw);
        $to = $from->next();
        $classNameMap = $this->normalizeClassNameMap($request->get_param('class_name_map'));

        $branchId = $this->currentUserBranchId();

        if ($branchId === null) {
            $requested = $request->get_param('branch_id');
            $branchId = $requested !== null && $requested !== '' ? (int) $requested : null;

            if ($branchId !== null && !$this->branchLookup->exists($branchId)) {
                return new WP_REST_Response(['message' => __('Geçersiz şube.', 'seviye-students')], 422);
            }
        }

        $candidates = $branchId !== null ? $this->students->findByBranch($branchId) : $this->students->all();
        $promoted = [];

        foreach ($candidates as $student) {
            if ($student->status !== StudentStatus::ACTIVE || $student->educationYear->value() !== $from->value()) {
                continue;
            }

            $newClassName = $classNameMap[$student->className] ?? $student->className;

            $updated = $this->students->update(
                $student->id,
                $student->branchId,
                $student->firstName,
                $student->lastName,
                $to,
                $newClassName,
                $student->status,
                $student->tcNo
            );

            $promoted[] = $this->serialize($updated);
        }

        return new WP_REST_Response([
            'promoted_count' => count($promoted),
            'to_education_year' => $to->value(),
            'promoted' => $promoted,
        ]);
    }

    /**
     * @param mixed $raw
     * @return array<string, string>
     */
    private function normalizeClassNameMap(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $map = [];

        foreach ($raw as $fromClassName => $toClassName) {
            if (is_string($fromClassName) && is_string($toClassName) && trim($toClassName) !== '') {
                $map[$fromClassName] = trim($toClassName);
            }
        }

        return $map;
    }

    /**
     * Öğrenci oluşturulurken aynı formda doldurulan veli bilgilerini
     * (varsa) işler: e-postayla eşleşen bir WP kullanıcısı varsa onu
     * kullanır (aynı velinin ikinci bir çocuğu eklenmesi durumu, bu
     * durumda yeni bir şifre ÜRETİLMEZ - mevcut hesabın kimlik bilgileri
     * değişmez), yoksa "Veli" rolünde, rastgele üretilmiş bir şifreyle
     * yeni bir WP kullanıcısı oluşturup öğrenciyle bağlar. T.C. Kimlik No
     * bu metodun işi değil - Security'nin `seviye/v1/security/users/{id}
     * /tc-no` REST uç noktası ayrıca çağrılır (Students, Security'nin
     * kimlik katmanına doğrudan/PHP seviyesinde bağımlı değil, Security
     * zaten Students'a bağımlı olduğu için tersi döngüsel bağımlılık
     * yaratırdı - bkz. docs/ARCHITECTURE.md).
     *
     * WordPress şifreleri düz metin olarak saklamadığı için üretilen şifre
     * yalnızca BU yanıtta bir kez görünür - `credentials` alanı bu yüzden
     * var, admin panelde "bir kerelik" bir bilgilendirme kartında gösterilir.
     *
     * Bu üretilen şifre de "kurum tarafından oluşturulan şifre" - Security'nin
     * `security.must_change_password` bayrağını (bkz.
     * Seviye\Security\Auth\MustChangePasswordGatewayInterface) buradan
     * DOĞRUDAN işaretleyemeyiz (yukarıdaki aynı döngüsel bağımlılık
     * kısıtı), bu yüzden `students.parent_password_generated` event'i
     * yayınlanır - SecurityModule bunu dinleyip bayrağı kendi işaretler
     * (bkz. HakedisEventListener'ın Commerce→Finance için kullandığı AYNI
     * gevşek bağlama deseni).
     *
     * Öğrenci zaten oluşturulduktan SONRA çalışır; burada bir hata olması
     * öğrenci kaydını geri almaz (bu kod tabanında hiçbir yerde DB
     * transaction kullanılmıyor) - başarısızlık durumunda çağıran,
     * `parent_error` alanıyla öğrencinin oluştuğunu ama velinin manuel
     * bağlanması gerektiğini bildirir.
     *
     * @return array{
     *     error: ?string,
     *     credentials: ?array{user_id: int, name: string, email: string, password: string},
     *     linked_existing: ?array{name: string, email: string}
     * }
     */
    private function maybeCreateAndLinkParent(Student $student, WP_REST_Request $request): array
    {
        $none = ['error' => null, 'credentials' => null, 'linked_existing' => null];

        $firstName = trim((string) $request->get_param('parent_first_name'));
        $lastName = trim((string) $request->get_param('parent_last_name'));
        $email = trim((string) $request->get_param('parent_email'));

        if ($firstName === '' && $lastName === '' && $email === '') {
            return $none;
        }

        if ($firstName === '' || $lastName === '' || $email === '' || !is_email($email)) {
            return [
                'error' => __('Veli eklenemedi: ad, soyad ve geçerli bir e-posta gerekli.', 'seviye-students'),
                'credentials' => null,
                'linked_existing' => null,
            ];
        }

        $relationship = ParentRelationship::tryFrom((string) $request->get_param('parent_relationship'))
            ?? ParentRelationship::MOTHER;

        $existingUser = get_user_by('email', $email);
        $userId = $existingUser !== false ? $existingUser->ID : null;
        $credentials = null;
        $linkedExisting = $existingUser !== false
            ? ['name' => $existingUser->display_name, 'email' => $email]
            : null;

        if ($userId === null) {
            $password = wp_generate_password(16, true);

            $created = wp_insert_user([
                'user_login' => $this->uniqueLoginFor($email),
                'user_email' => $email,
                'user_pass' => $password,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => $firstName . ' ' . $lastName,
                'role' => Role::VELI->value,
            ]);

            if (is_wp_error($created)) {
                return [
                    'error' => sprintf(
                        // translators: %s is the underlying WordPress error message.
                        __('Veli oluşturulamadı: %s', 'seviye-students'),
                        $created->get_error_message()
                    ),
                    'credentials' => null,
                    'linked_existing' => null,
                ];
            }

            $userId = (int) $created;
            $credentials = [
                'user_id' => $userId,
                'name' => $firstName . ' ' . $lastName,
                'email' => $email,
                'password' => $password,
            ];

            $this->eventBus->dispatch(new Event('students.parent_password_generated', ['user_id' => $userId]));
        }

        try {
            $this->studentParents->link($student->id, $userId, $relationship);
        } catch (\Throwable $exception) {
            return [
                'error' => sprintf(
                    '%s: %s (%s:%d)',
                    get_class($exception),
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                ),
                'credentials' => $credentials,
                'linked_existing' => $linkedExisting,
            ];
        }

        return ['error' => null, 'credentials' => $credentials, 'linked_existing' => $linkedExisting];
    }

    /**
     * Mirrors {@see \Seviye\Security\Http\Admin\UserListPage::uniqueLoginFor()} -
     * duplicated rather than shared since Security publishes no Contract for
     * user provisioning (see docs/ARCHITECTURE.md, module boundary rules).
     */
    private function uniqueLoginFor(string $email): string
    {
        $base = sanitize_user(strstr($email, '@', true) ?: $email, true);
        $base = $base !== '' ? $base : 'veli';
        $login = $base;
        $suffix = 2;

        while (username_exists($login)) {
            $login = $base . '-' . $suffix;
            $suffix++;
        }

        return $login;
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $branchId = $this->resolveBranchIdForWrite($request);

        if ($branchId === null || !$this->branchLookup->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Geçersiz şube.', 'seviye-students')], 422);
        }

        try {
            $student = $this->students->update(
                $id,
                $branchId,
                (string) $request->get_param('first_name'),
                (string) $request->get_param('last_name'),
                EducationYear::fromString((string) $request->get_param('education_year')),
                (string) $request->get_param('class_name'),
                StudentStatus::from((string) ($request->get_param('status') ?? StudentStatus::ACTIVE->value)),
                $this->resolveStudentTcNo($request)
            );
        } catch (InvalidArgumentException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 422);
        }

        return new WP_REST_Response($this->serialize($student));
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $this->students->delete($id);
        } catch (\Throwable $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 409);
        }

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Öğrenciyle bağlı velilerin ad/e-postasını da döndürür - ham
     * `parent_user_id` listesi paneldeki "Veliler" bölümünde hangi velinin
     * hangisi olduğunu göstermeye yetmiyordu. `get_userdata()` çekirdek
     * WordPress fonksiyonu, Security'nin kimlik katmanına bağımlılık
     * gerektirmiyor (bkz. docs/ARCHITECTURE.md, modül sınırı kuralları).
     */
    public function listParents(WP_REST_Request $request): WP_REST_Response
    {
        $parentIds = $this->studentParents->parentUserIdsForStudent((int) $request->get_param('id'));

        $parents = array_values(array_filter(array_map(
            static function (int $id): ?array {
                $user = get_userdata($id);

                return $user !== false
                    ? ['id' => $id, 'name' => $user->display_name, 'email' => $user->user_email]
                    : null;
            },
            $parentIds
        )));

        return new WP_REST_Response($parents);
    }

    public function linkParent(WP_REST_Request $request): WP_REST_Response
    {
        $relationship = ParentRelationship::tryFrom((string) $request->get_param('relationship'));

        if ($relationship === null) {
            return new WP_REST_Response(['message' => __('Geçersiz veli ilişki türü.', 'seviye-students')], 422);
        }

        try {
            $this->studentParents->link(
                (int) $request->get_param('id'),
                (int) $request->get_param('parent_user_id'),
                $relationship
            );
        } catch (\Throwable $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 500);
        }

        return new WP_REST_Response(['success' => true]);
    }

    public function unlinkParent(WP_REST_Request $request): WP_REST_Response
    {
        $this->studentParents->unlink((int) $request->get_param('id'), (int) $request->get_param('parent_user_id'));

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Velinin ad/e-postasını düzenler - `canAccessStudent` yalnızca URL'deki
     * öğrenci id'sinin çağıranın kapsamında olduğunu doğruluyor, veli
     * hesabının GERÇEKTEN o öğrenciyle bağlı olduğunu doğrulamıyor. Bu
     * yüzden burada AYRICA `parentUserIdsForStudent()` ile kontrol ediliyor
     * - aksi halde bir Şube Müdürü, kendi şubesinden geçerli bir öğrenci
     * id'si + kapsamı dışındaki RASTGELE bir kullanıcı id'si vererek o
     * kullanıcının hesabını (bağlı olmasa bile) düzenleyebilirdi.
     */
    public function updateParent(WP_REST_Request $request): WP_REST_Response
    {
        $studentId = (int) $request->get_param('id');
        $parentUserId = (int) $request->get_param('parent_user_id');

        if (!in_array($parentUserId, $this->studentParents->parentUserIdsForStudent($studentId), true)) {
            return new WP_REST_Response(['message' => __('Veli bu öğrenciyle bağlı değil.', 'seviye-students')], 404);
        }

        $user = get_userdata($parentUserId);

        if ($user === false || in_array('administrator', $user->roles, true)) {
            return new WP_REST_Response(['message' => __('Geçersiz veli hesabı.', 'seviye-students')], 404);
        }

        $displayName = trim((string) $request->get_param('display_name'));
        $email = trim((string) $request->get_param('email'));

        if ($displayName === '' || $email === '' || !is_email($email)) {
            return new WP_REST_Response(
                ['message' => __('Ad Soyad ve geçerli bir e-posta gerekli.', 'seviye-students')],
                422
            );
        }

        $existingByEmail = email_exists($email);

        if ($existingByEmail !== false && (int) $existingByEmail !== $parentUserId) {
            return new WP_REST_Response(
                ['message' => __('Bu e-posta zaten başka bir kullanıcıya ait.', 'seviye-students')],
                422
            );
        }

        $updated = wp_update_user(['ID' => $parentUserId, 'display_name' => $displayName, 'user_email' => $email]);

        if (is_wp_error($updated)) {
            return new WP_REST_Response(['message' => $updated->get_error_message()], 500);
        }

        return new WP_REST_Response(['id' => $parentUserId, 'name' => $displayName, 'email' => $email]);
    }

    /**
     * Branch-scoped staff (Şube Müdürü) always write into their own branch,
     * regardless of what the request body says; HQ must supply one.
     */
    private function resolveBranchIdForWrite(WP_REST_Request $request): ?int
    {
        $ownBranchId = $this->currentUserBranchId();

        if ($ownBranchId !== null) {
            return $ownBranchId;
        }

        $requested = $request->get_param('branch_id');

        return $requested !== null ? (int) $requested : null;
    }

    private function currentUserBranchId(): ?int
    {
        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Student $student): array
    {
        return [
            'id' => $student->id,
            'branch_id' => $student->branchId,
            'branch_name' => $this->branchLookup->find($student->branchId)?->name,
            'first_name' => $student->firstName,
            'last_name' => $student->lastName,
            'education_year' => $student->educationYear->value(),
            'class_name' => $student->className,
            'tc_no' => $student->tcNo,
            'status' => $student->status->value,
            'photo_url' => $student->photoAttachmentId !== null
                ? wp_get_attachment_image_url($student->photoAttachmentId, 'thumbnail') ?: null
                : null,
        ];
    }

    /**
     * Öğrencinin KENDİ T.C. Kimlik No'su - Security'nin veli/personel giriş
     * kimliğinden (scp_user_identities, TcNumber checksum'lı) tamamen ayrı,
     * yalnızca bilgi amaçlı bir öğrenci kaydı alanı (öğrenciler WP
     * kullanıcısı değil, giriş yapmıyor). Bu yüzden tam checksum
     * doğrulaması yerine yalnızca biçim (11 hane) kontrol ediliyor - Security
     * Contract yayınlamadığı için Students, TcNumber'a bağımlı olamaz (bkz.
     * docs/ARCHITECTURE.md).
     */
    private function resolveStudentTcNo(WP_REST_Request $request): ?string
    {
        return $this->validateTcNo(trim((string) $request->get_param('tc_no')));
    }

    /**
     * Shared by resolveStudentTcNo() (single create/update via the form)
     * and import() (one call per CSV row) - both need the exact same
     * validation, so it lives once here rather than being duplicated.
     */
    private function validateTcNo(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        if (!preg_match('/^\d{11}$/', $raw)) {
            $message = __('Geçersiz T.C. Kimlik No biçimi (11 hane olmalı).', 'seviye-students');
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new InvalidArgumentException($message);
        }

        return $raw;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function writableArgs(): array
    {
        return [
            'branch_id' => ['required' => false, 'type' => 'integer'],
            'first_name' => ['required' => true, 'type' => 'string'],
            'last_name' => ['required' => true, 'type' => 'string'],
            'education_year' => ['required' => true, 'type' => 'string'],
            'class_name' => ['required' => true, 'type' => 'string'],
            'tc_no' => ['required' => false, 'type' => 'string'],
            'status' => ['required' => false, 'type' => 'string'],
            // Yalnızca öğrenci OLUŞTURULURKEN (store()) kullanılır - update()
            // aynı args'ı paylaşıyor ama bu alanları okumuyor, zararsız.
            'parent_first_name' => ['required' => false, 'type' => 'string'],
            'parent_last_name' => ['required' => false, 'type' => 'string'],
            'parent_email' => ['required' => false, 'type' => 'string'],
            'parent_relationship' => ['required' => false, 'type' => 'string'],
        ];
    }
}
