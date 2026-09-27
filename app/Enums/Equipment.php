<?php

namespace App\Enums;

/**
 * Machines et équipements de la salle — la bibliothèque se range aussi par là,
 * parce qu'on cherche souvent « ce qu'on peut faire sur la machine libre ».
 */
enum Equipment: string
{
    case Presse = 'presse';
    case HackSquat = 'hack';
    case LegExtension = 'legext';
    case LegCurl = 'legcurl';
    case Smith = 'smith';
    case Rack = 'rack';
    case Banc = 'banc';
    case ChestPress = 'chest';
    case PecDeck = 'pec';
    case Poulie = 'poulie';
    case TirageVertical = 'lat';
    case TirageHorizontal = 'row';
    case TBar = 'tbar';
    case ShoulderPress = 'shoulder';
    case Mollets = 'mollets';
    case Abducteurs = 'abduc';
    case HipThrust = 'hip';
    case GluteKickback = 'glute';
    case ChaiseRomaine = 'romaine';
    case BarreTraction = 'traction';
    case BarresParalleles = 'dips';
    case TractionAssistee = 'assist';
    case Halteres = 'halteres';
    case Barre = 'barre';
    case Kettlebell = 'kb';
    case PoidsDuCorps = 'pdc';
    case Trx = 'trx';
    case BancAbdos = 'abdos';
    case Tapis = 'tapis';
    case Velo = 'velo';
    case AirBike = 'airbike';
    case Rameur = 'rameur';
    case Elliptique = 'ellip';
    case Stepper = 'stepper';
    case SkiErg = 'ski';
    case Corde = 'corde';

    public function label(): string
    {
        return match ($this) {
            self::Presse => 'Presse à cuisses',
            self::HackSquat => 'Hack squat',
            self::LegExtension => 'Leg extension',
            self::LegCurl => 'Leg curl',
            self::Smith => 'Smith machine',
            self::Rack => 'Rack à squat',
            self::Banc => 'Banc de musculation',
            self::ChestPress => 'Chest press',
            self::PecDeck => 'Pec deck',
            self::Poulie => 'Poulie / vis-à-vis',
            self::TirageVertical => 'Tirage vertical',
            self::TirageHorizontal => 'Tirage horizontal',
            self::TBar => 'T-bar row',
            self::ShoulderPress => 'Shoulder press',
            self::Mollets => 'Machine à mollets',
            self::Abducteurs => 'Abducteurs / adducteurs',
            self::HipThrust => 'Hip thrust machine',
            self::GluteKickback => 'Glute kickback',
            self::ChaiseRomaine => 'Chaise romaine',
            self::BarreTraction => 'Barre de traction',
            self::BarresParalleles => 'Barres parallèles',
            self::TractionAssistee => 'Traction assistée',
            self::Halteres => 'Haltères',
            self::Barre => 'Barre olympique',
            self::Kettlebell => 'Kettlebell',
            self::PoidsDuCorps => 'Poids du corps',
            self::Trx => 'Sangles TRX',
            self::BancAbdos => 'Banc abdos',
            self::Tapis => 'Tapis de course',
            self::Velo => 'Vélo',
            self::AirBike => 'Air bike',
            self::Rameur => 'Rameur',
            self::Elliptique => 'Elliptique',
            self::Stepper => 'Stepper / escalier',
            self::SkiErg => 'SkiErg',
            self::Corde => 'Corde à sauter',
        };
    }
}
