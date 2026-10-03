<?php

namespace App\Enums;

/**
 * Machines et équipements d'une salle comme L'Appart Fitness — guidées (Matrix,
 * EGYM), charge libre iso-latérale (Hammer Strength, Panatta), poulies, barres,
 * zone cross-training / Hyrox et cardio. La bibliothèque se range aussi par
 * là, parce qu'on cherche souvent « ce qu'on peut faire sur la machine libre ».
 */
enum Equipment: string
{
    case ChestPress = 'chest-press';
    case InclinePress = 'incline-press';
    case DeclinePress = 'decline-press';
    case PecDeck = 'pec-deck';
    case ShoulderPress = 'shoulder-press';
    case LateralMachine = 'lateral-machine';
    case LatPulldown = 'lat-pulldown';
    case SeatedRow = 'seated-row';
    case PulloverMachine = 'pullover-machine';
    case BackMachine = 'back-machine';
    case BicepsMachine = 'biceps-machine';
    case TricepsMachine = 'triceps-machine';
    case DipsMachine = 'dips-machine';
    case Assisted = 'assisted';
    case LegPress = 'leg-press';
    case LegPressFortyFive = 'leg-press-45';
    case LegExtension = 'leg-extension';
    case LegCurlSeated = 'leg-curl-seated';
    case LegCurlLying = 'leg-curl-lying';
    case LegCurlStanding = 'leg-curl-standing';
    case HipMachine = 'hip-machine';
    case MultiHip = 'multi-hip';
    case GluteMachine = 'glute-machine';
    case HipThrustMachine = 'hip-thrust-machine';
    case CalfStanding = 'calf-standing';
    case CalfSeated = 'calf-seated';
    case AbMachine = 'ab-machine';
    case RotaryTorso = 'rotary-torso';
    case HackSquat = 'hack-squat';
    case PendulumSquat = 'pendulum-squat';
    case VSquat = 'v-squat';
    case BeltSquat = 'belt-squat';
    case IsoChest = 'iso-chest';
    case IsoShoulder = 'iso-shoulder';
    case IsoRow = 'iso-row';
    case IsoPulldown = 'iso-pulldown';
    case TBar = 't-bar';
    case Smith = 'smith';
    case Rack = 'rack';
    case Barbell = 'barbell';
    case Plate = 'plate';
    case EzBar = 'ez-bar';
    case TrapBar = 'trap-bar';
    case Dumbbells = 'dumbbells';
    case Bench = 'bench';
    case Preacher = 'preacher';
    case RomanChair = 'roman-chair';
    case Ghd = 'ghd';
    case CaptainChair = 'captain-chair';
    case PullUpBar = 'pull-up-bar';
    case ParallelBars = 'parallel-bars';
    case Cable = 'cable';
    case Crossover = 'crossover';
    case Landmine = 'landmine';
    case Kettlebell = 'kettlebell';
    case Sled = 'sled';
    case BattleRope = 'battle-rope';
    case Sandbag = 'sandbag';
    case WallBall = 'wall-ball';
    case SlamBall = 'slam-ball';
    case PlyoBox = 'plyo-box';
    case Rings = 'rings';
    case Trx = 'trx';
    case Bands = 'bands';
    case SwissBall = 'swiss-ball';
    case AbWheel = 'ab-wheel';
    case JumpRope = 'jump-rope';
    case PunchingBag = 'punching-bag';
    case Bodyweight = 'bodyweight';
    case Mat = 'mat';
    case FoamRoller = 'foam-roller';
    case Treadmill = 'treadmill';
    case CurveTreadmill = 'curve-treadmill';
    case Bike = 'bike';
    case RecumbentBike = 'recumbent-bike';
    case SpinBike = 'spin-bike';
    case AirBike = 'air-bike';
    case Elliptical = 'elliptical';
    case Stepper = 'stepper';
    case StairClimber = 'stair-climber';
    case Rower = 'rower';
    case Skierg = 'skierg';
    case ArmErgometer = 'arm-ergometer';

    public function label(): string
    {
        return match ($this) {
            self::ChestPress => 'Presse pectorale',
            self::InclinePress => 'Développé incliné machine',
            self::DeclinePress => 'Développé décliné machine',
            self::PecDeck => 'Pec deck / oiseau',
            self::ShoulderPress => 'Presse épaules',
            self::LateralMachine => 'Élévations latérales machine',
            self::LatPulldown => 'Tirage vertical',
            self::SeatedRow => 'Tirage horizontal',
            self::PulloverMachine => 'Pullover machine',
            self::BackMachine => 'Lombaires machine',
            self::BicepsMachine => 'Curl biceps machine',
            self::TricepsMachine => 'Triceps machine',
            self::DipsMachine => 'Dips machine assis',
            self::Assisted => 'Traction / dips assistés',
            self::LegPress => 'Presse à cuisses',
            self::LegPressFortyFive => 'Presse à cuisses 45°',
            self::LegExtension => 'Leg extension',
            self::LegCurlSeated => 'Leg curl assis',
            self::LegCurlLying => 'Leg curl allongé',
            self::LegCurlStanding => 'Leg curl debout',
            self::HipMachine => 'Abducteurs / adducteurs',
            self::MultiHip => 'Multi-hip',
            self::GluteMachine => 'Machine fessiers',
            self::HipThrustMachine => 'Hip thrust machine',
            self::CalfStanding => 'Mollets debout machine',
            self::CalfSeated => 'Mollets assis machine',
            self::AbMachine => 'Crunch machine',
            self::RotaryTorso => 'Rotation du buste',
            self::HackSquat => 'Hack squat',
            self::PendulumSquat => 'Pendulum squat',
            self::VSquat => 'V-squat',
            self::BeltSquat => 'Belt squat',
            self::IsoChest => 'Développé iso-latéral (Hammer)',
            self::IsoShoulder => 'Presse épaules iso-latérale',
            self::IsoRow => 'Rowing iso-latéral (bas / haut)',
            self::IsoPulldown => 'Tirage iso-latéral',
            self::TBar => 'T-bar row',
            self::Smith => 'Smith machine',
            self::Rack => 'Cage à squat',
            self::Barbell => 'Barre olympique',
            self::Plate => 'Disque',
            self::EzBar => 'Barre EZ',
            self::TrapBar => 'Barre hexagonale',
            self::Dumbbells => 'Haltères',
            self::Bench => 'Banc de musculation',
            self::Preacher => 'Pupitre à curl',
            self::RomanChair => 'Chaise romaine / lombaires 45°',
            self::Ghd => 'GHD',
            self::CaptainChair => 'Chaise capitaine',
            self::PullUpBar => 'Barre de traction',
            self::ParallelBars => 'Barres parallèles',
            self::Cable => 'Poulie',
            self::Crossover => 'Poulie vis-à-vis / functional trainer',
            self::Landmine => 'Landmine',
            self::Kettlebell => 'Kettlebell',
            self::Sled => 'Sled (traîneau)',
            self::BattleRope => 'Corde ondulatoire',
            self::Sandbag => 'Sac lesté',
            self::WallBall => 'Wall ball / médecine ball',
            self::SlamBall => 'Slam ball',
            self::PlyoBox => 'Box de saut',
            self::Rings => 'Anneaux',
            self::Trx => 'Sangles TRX',
            self::Bands => 'Élastiques',
            self::SwissBall => 'Swiss ball',
            self::AbWheel => 'Roue abdominale',
            self::JumpRope => 'Corde à sauter',
            self::PunchingBag => 'Sac de frappe',
            self::Bodyweight => 'Poids du corps',
            self::Mat => 'Tapis de sol',
            self::FoamRoller => 'Rouleau de massage',
            self::Treadmill => 'Tapis de course',
            self::CurveTreadmill => 'Tapis curve (non motorisé)',
            self::Bike => 'Vélo droit',
            self::RecumbentBike => 'Vélo semi-allongé',
            self::SpinBike => 'Vélo de biking',
            self::AirBike => 'Air bike',
            self::Elliptical => 'Elliptique',
            self::Stepper => 'Stepper',
            self::StairClimber => 'Escalier',
            self::Rower => 'Rameur',
            self::Skierg => 'SkiErg',
            self::ArmErgometer => 'Ergomètre à bras',
        };
    }

    public function kind(): EquipmentKind
    {
        return match ($this) {
            self::ChestPress, self::InclinePress, self::DeclinePress, self::PecDeck, self::ShoulderPress,
            self::LateralMachine, self::LatPulldown, self::SeatedRow, self::PulloverMachine,
            self::BackMachine, self::BicepsMachine, self::TricepsMachine, self::DipsMachine, self::Assisted,
            self::LegPress, self::LegPressFortyFive, self::LegExtension, self::LegCurlSeated,
            self::LegCurlLying, self::LegCurlStanding, self::HipMachine, self::MultiHip, self::GluteMachine,
            self::HipThrustMachine, self::CalfStanding, self::CalfSeated, self::AbMachine, self::RotaryTorso,
            self::HackSquat, self::PendulumSquat, self::VSquat, self::BeltSquat, self::IsoChest,
            self::IsoShoulder, self::IsoRow, self::IsoPulldown, self::TBar, self::Smith, self::Cable,
            self::Crossover => EquipmentKind::Machine,
            self::Rack, self::Barbell, self::Plate, self::EzBar, self::TrapBar, self::Dumbbells, self::Bench,
            self::Preacher, self::Landmine, self::Kettlebell => EquipmentKind::Free,
            self::RomanChair, self::Ghd, self::CaptainChair, self::PullUpBar, self::ParallelBars,
            self::Rings, self::Trx, self::Bands, self::SwissBall, self::AbWheel, self::Bodyweight, self::Mat,
            self::FoamRoller, self::PlyoBox => EquipmentKind::Bodyweight,
            self::Sled, self::BattleRope, self::Sandbag, self::WallBall, self::SlamBall, self::JumpRope,
            self::PunchingBag, self::Treadmill, self::CurveTreadmill, self::Bike, self::RecumbentBike,
            self::SpinBike, self::AirBike, self::Elliptical, self::Stepper, self::StairClimber, self::Rower,
            self::Skierg, self::ArmErgometer => EquipmentKind::Conditioning,
        };
    }
}
