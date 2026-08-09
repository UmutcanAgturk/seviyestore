<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Students\Domain\EducationYear;
use Seviye\Students\Domain\Student;
use Seviye\Students\Domain\StudentStatus;

interface StudentRepositoryInterface
{
    public function create(
        int $branchId,
        string $firstName,
        string $lastName,
        EducationYear $educationYear,
        string $className,
        ?string $tcNo = null
    ): Student;

    public function update(
        int $id,
        int $branchId,
        string $firstName,
        string $lastName,
        EducationYear $educationYear,
        string $className,
        StudentStatus $status,
        ?string $tcNo = null
    ): Student;

    public function find(int $id): ?Student;

    /**
     * "Öğrenci profiline fotoğraf/avatar yükleme" - dar bir amaca özel
     * yazma metodu (tüm Student alanlarını isteyen update()'in geniş
     * imzasını YENİDEN KULLANMIYOR), çünkü fotoğraf yükleme veliden
     * gelir ve velinin şube/eğitim yılı/T.C. no gibi alanları değiştirme
     * yetkisi/bilgisi yok - yalnızca $attachmentId'yi değiştiriyor.
     */
    public function updatePhoto(int $id, ?int $attachmentId): void;

    public function delete(int $id): void;

    /**
     * @return list<Student>
     */
    public function all(): array;

    /**
     * @return list<Student>
     */
    public function findByBranch(int $branchId): array;
}
