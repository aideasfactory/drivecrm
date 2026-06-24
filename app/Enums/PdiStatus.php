<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * UK Potential Driving Instructor (PDI) lifecycle stages.
 *
 * Part 1 = Theory, Part 2 = Driving Ability, Part 3 = Instructional Ability.
 * After Part 3, the instructor becomes a fully Qualified ADI.
 */
enum PdiStatus: string
{
    case Qualified = 'qualified';
    case Trainee = 'trainee';
    case PdiPart1 = 'pdi_part_1';
    case PdiPart2 = 'pdi_part_2';
    case PdiPart3 = 'pdi_part_3';

    public function label(): string
    {
        return match ($this) {
            self::Qualified => 'Qualified ADI',
            self::Trainee => 'Trainee',
            self::PdiPart1 => 'PDI Part 1 (Theory)',
            self::PdiPart2 => 'PDI Part 2 (Driving)',
            self::PdiPart3 => 'PDI Part 3 (Instructional)',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
