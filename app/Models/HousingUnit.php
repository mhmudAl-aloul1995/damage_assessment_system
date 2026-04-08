<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Container\Attributes\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute; // Import Attribute if using modern Laravel accessors

/**
 * Class HousingUnit
 *
 * @property int $id
 * @property int|null $objectid
 * @property string|null $globalid
 * @property string|null $housing_unit_type
 * @property string|null $unit_damage_status
 * @property string|null $floor_number
 * @property string|null $housing_unit_number
 * @property string|null $unit_direction
 * @property int|null $damaged_area_m2
 * @property string|null $infra_type2
 * @property string|null $house_unit_ownership
 * @property string|null $other_ownership
 * @property string|null $occupied
 * @property int|null $number_of_rooms
 * @property string|null $identity_type1
 * @property int|null $id_number1
 * @property string|null $passport1
 * @property string|null $other_id1
 * @property string|null $unit_owner
 * @property string|null $q_9_3_1_first_name
 * @property string|null $q_9_3_2_second_name__father
 * @property string|null $q_9_3_3_third_name__grandfather
 * @property string|null $q_9_3_4_last_name
 * @property string|null $sex
 * @property int|null $mobile_number
 * @property int|null $additional_mobile
 * @property string|null $owner_job
 * @property string|null $other_job
 * @property int|null $age
 * @property string|null $marital_status
 * @property string|null $no_spouses
 * @property string|null $spouse1
 * @property int|null $spouse1_id
 * @property string|null $spouse2
 * @property string|null $spouse2_id
 * @property string|null $spouse3
 * @property string|null $spouse3_id
 * @property string|null $spouse4
 * @property string|null $spouse4_id
 * @property string|null $are_there_people_with_disability
 * @property string|null $number_of_people_with_disability
 * @property string|null $handicapped_type
 * @property string|null $other_handicapped
 * @property string|null $is_refugee
 * @property string|null $unrwa_registration_number
 * @property string|null $number_of_nuclear_families
 * @property string|null $mchildren_001
 * @property string|null $myoung
 * @property string|null $melderly
 * @property string|null $fchildren
 * @property string|null $fyoung_001
 * @property string|null $felderly
 * @property string|null $pregnant
 * @property string|null $lactating
 * @property string|null $the_unit_resident
 * @property string|null $current_address
 * @property string|null $current_residence
 * @property string|null $current_residence_other
 * @property string|null $shelter_name
 * @property string|null $shelter_type
 * @property string|null $shelter_type_other
 * @property string|null $governorate
 * @property string|null $locality
 * @property string|null $neighborhood
 * @property string|null $street
 * @property string|null $closest_facility2
 * @property string|null $identity_type2
 * @property string|null $rentee_id_passport_number
 * @property string|null $rentee_resident_full_name
 * @property string|null $q_13_3_1_first_name
 * @property string|null $q_13_3_2_second_name__father
 * @property string|null $q_13_3_3_third_name__grandfather
 * @property string|null $q_13_3_4_last_name__family
 * @property string|null $rentee_mobile_number
 * @property string|null $work_type
 * @property string|null $other_work
 * @property string|null $external_finishing_of_the_unit
 * @property string|null $other_external_finishing
 * @property string|null $is_finished
 * @property string|null $finishing_extent
 * @property string|null $internal_finishing_of_the_unit
 * @property string|null $finishing_partial_types
 * @property string|null $has_fire
 * @property string|null $fire_extent
 * @property string|null $fire_severity
 * @property string|null $fire_locations
 * @property string|null $fire_rooms_count
 * @property string|null $fire_area
 * @property string|null $furniture_ownership
 * @property string|null $percentage_of_damaged_furniture
 * @property string|null $unit_stripping
 * @property string|null $unit_stripping_details
 * @property string|null $stripping_area
 * @property string|null $stripping_locations
 * @property string|null $rubble_removal_is_needed
 * @property string|null $activation_of_uxo_ha_d_material_clearance
 * @property string|null $unit_support_needed
 * @property string|null $is_the_housing_unit_or_living_habitable
 * @property string|null $mhpss_experinced
 * @property string|null $other_mhpss_exp
 * @property string|null $mhpss_support
 * @property string|null $other_mhpss_support
 * @property string|null $community_participation
 * @property string|null $ce1
 * @property string|null $prefab_moving
 * @property string|null $prefab_moving_maybe
 * @property string|null $prefab_types
 * @property string|null $other_prefab_types
 * @property string|null $prefab_pref
 * @property string|null $ce2
 * @property string|null $reh_kitchen
 * @property string|null $reh_bathroom
 * @property string|null $reh_type
 * @property string|null $ce3
 * @property string|null $additional_comments
 * @property string|null $dm1
 * @property string|null $dm2
 * @property string|null $dm3
 * @property string|null $dm4
 * @property string|null $dm5
 * @property string|null $dm6
 * @property string|null $dm7
 * @property string|null $dm8
 * @property string|null $dm9
 * @property string|null $dm10
 * @property string|null $dm11
 * @property string|null $dm12
 * @property string|null $bl2
 * @property string|null $bl3
 * @property string|null $bl4
 * @property string|null $bl5
 * @property string|null $co2
 * @property string|null $co3
 * @property string|null $co4
 * @property string|null $co5
 * @property string|null $co6
 * @property string|null $co7
 * @property string|null $co8
 * @property string|null $co9
 * @property string|null $co10
 * @property string|null $fn1
 * @property string|null $fn2
 * @property string|null $fn3
 * @property string|null $fn4
 * @property string|null $fn5
 * @property string|null $fn6
 * @property string|null $fn7
 * @property string|null $fn8
 * @property string|null $fn10
 * @property string|null $fn11
 * @property string|null $fn12
 * @property string|null $fn13
 * @property string|null $fn14
 * @property string|null $fn15
 * @property string|null $fn22
 * @property string|null $fn23
 * @property string|null $fn24
 * @property string|null $fn25
 * @property string|null $fn26
 * @property string|null $fn16
 * @property string|null $fn17
 * @property string|null $fn18
 * @property string|null $fn19
 * @property string|null $fn20
 * @property string|null $fn21
 * @property string|null $fn27
 * @property string|null $fn28
 * @property string|null $fn29
 * @property string|null $fn30
 * @property string|null $fn31
 * @property string|null $al1
 * @property string|null $al2
 * @property string|null $al3
 * @property string|null $al4
 * @property string|null $al5
 * @property string|null $al6
 * @property string|null $al7
 * @property string|null $al8
 * @property string|null $al9
 * @property string|null $al10
 * @property string|null $wd1
 * @property string|null $wd3
 * @property string|null $wd4
 * @property string|null $wd5
 * @property string|null $wd6
 * @property string|null $wd7
 * @property string|null $wd8
 * @property string|null $wd9
 * @property string|null $wd10
 * @property string|null $wd11
 * @property string|null $wd12
 * @property string|null $mt1
 * @property string|null $mt2
 * @property string|null $mt3
 * @property string|null $mt4
 * @property string|null $mt5
 * @property string|null $mt6
 * @property string|null $mt7
 * @property string|null $mt8
 * @property string|null $mt9
 * @property string|null $mt10
 * @property string|null $mt11
 * @property string|null $mt12
 * @property string|null $mt13
 * @property string|null $mt14
 * @property string|null $mt15
 * @property string|null $mt16
 * @property string|null $mt17
 * @property string|null $mt19
 * @property string|null $cm1
 * @property string|null $cm2
 * @property string|null $cm3
 * @property string|null $cm4
 * @property string|null $cm5
 * @property string|null $cm6
 * @property string|null $cm7
 * @property string|null $cm8
 * @property string|null $cm9
 * @property string|null $cm10
 * @property string|null $cm11
 * @property string|null $pm1
 * @property string|null $pm2
 * @property string|null $pm101
 * @property string|null $pm18
 * @property string|null $pm19
 * @property string|null $pm3
 * @property string|null $pm4
 * @property string|null $pm5
 * @property string|null $pm6
 * @property string|null $pm7
 * @property string|null $pm8
 * @property string|null $pm9
 * @property string|null $pm10
 * @property string|null $pm11
 * @property string|null $pm12
 * @property string|null $pm13
 * @property string|null $pm14
 * @property string|null $pm15
 * @property string|null $pm16
 * @property string|null $pm20
 * @property string|null $pm21
 * @property string|null $pm22
 * @property string|null $pm23
 * @property string|null $pm24
 * @property string|null $pm25
 * @property string|null $pm26
 * @property string|null $pm27
 * @property string|null $pm28
 * @property string|null $pm29
 * @property string|null $pm30
 * @property string|null $pm31
 * @property string|null $pm32
 * @property string|null $pm33
 * @property string|null $pm34
 * @property string|null $pm35
 * @property string|null $pm36
 * @property string|null $pm37
 * @property string|null $pm38
 * @property string|null $pm39
 * @property string|null $el1
 * @property string|null $el2
 * @property string|null $el3
 * @property string|null $el4
 * @property string|null $el5
 * @property string|null $el6
 * @property string|null $el7
 * @property string|null $el8
 * @property string|null $el9
 * @property string|null $el10
 * @property string|null $el11
 * @property string|null $el12
 * @property string|null $el13
 * @property string|null $el14
 * @property string|null $el15
 * @property string|null $el16
 * @property string|null $el17
 * @property string|null $el18
 * @property string|null $el19
 * @property string|null $el20
 * @property string|null $el21
 * @property string|null $el22
 * @property string|null $el23
 * @property string|null $el24
 * @property string|null $el25
 * @property string|null $el26
 * @property string|null $el27
 * @property string|null $el28
 * @property string|null $el29
 * @property string|null $el30
 * @property string|null $pv_note
 * @property string|null $pv1
 * @property string|null $pv2
 * @property string|null $pv3
 * @property string|null $pv4
 * @property string|null $pv5
 * @property string|null $pv6
 * @property string|null $pv7
 * @property string|null $pv8
 * @property string|null $pv9
 * @property string|null $pv10
 * @property string|null $pv11
 * @property string|null $pv12
 * @property string|null $item1
 * @property string|null $quant1
 * @property string|null $item2
 * @property string|null $quant2
 * @property string|null $item3
 * @property string|null $quant3
 * @property string|null $item4
 * @property string|null $quant4
 * @property string|null $item5
 * @property string|null $quant5
 * @property string|null $final_comments
 * @property string|null $parentglobalid
 * @property string|null $creationdate
 * @property string|null $creator
 * @property string|null $editdate
 * @property string|null $editor
 * @property string|null $al11
 * @property string|null $cm12
 * @property string|null $cm13
 * @property string|null $pm40
 */
class HousingUnit extends Model
{
    protected $table = 'housing_units';

    /**
     * @var string
     */
    protected $connection = 'mysql';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */


       protected $fillable = [
            'id',
            'attachments',

            'objectid',
            'globalid',
            'housing_unit_type',
            'unit_damage_status',
            'floor_number',
            'housing_unit_number',
            'unit_direction',
            'damaged_area_m2',
            'infra_type2',
            'house_unit_ownership',
            'other_ownership',
            'occupied',
            'empty_land_rhu',
            'number_of_rooms',
            'identity_type1',
            'id_number1',
            'passport1',
            'other_id1',
            'unit_owner',
            'agreement_duration',
            'q_9_3_1_first_name',
            'q_9_3_2_second_name__father',
            'q_9_3_3_third_name__grandfather',
            'q_9_3_4_last_name',
            'sex',
            'mobile_number',
            'additional_mobile',
            'owner_job',
            'other_job',
            'age',
            'marital_status',
            'no_spouses',
            'spouse1',
            'spouse1_id',
            'spouse2',
            'spouse2_id',
            'spouse3',
            'spouse3_id',
            'spouse4',
            'spouse4_id',
            'are_there_people_with_disability',
            'number_of_people_with_disability',
            'handicapped_type',
            'other_handicapped',
            'is_refugee',
            'unrwa_registration_number',
            'number_of_nuclear_families',
            'mchildren_001',
            'myoung',
            'melderly',
            'fchildren',
            'fyoung_001',
            'felderly',
            'pregnant',
            'lactating',
            'the_unit_resident',
            'current_address',
            'current_residence',
            'current_residence_other',
            'shelter_name',
            'shelter_type',
            'shelter_type_other',
            'governorate',
            'locality',
            'neighborhood',
            'street',
            'closest_facility2',
            'identity_type2',
            'rentee_id_passport_number',
            'rentee_resident_full_name',
            'q_13_3_1_first_name',
            'q_13_3_2_second_name__father',
            'q_13_3_3_third_name__grandfather',
            'q_13_3_4_last_name__family',
            'rentee_mobile_number',
            'work_type',
            'other_work',
            'external_finishing_of_the_unit',
            'other_external_finishing',
            'is_finished',
            'finishing_extent',
            'internal_finishing_of_the_unit',
            'finishing_partial_types',
            'has_fire',
            'fire_extent',
            'fire_severity',
            'fire_locations',
            'fire_rooms_count',
            'fire_area',
            'furniture_ownership',
            'percentage_of_damaged_furniture',
            'unit_stripping',
            'unit_stripping_details',
            'stripping_area',
            'stripping_locations',
            'rubble_removal_is_needed',
            'activation_of_uxo_ha_d_material_clearance',
            'unit_support_needed',
            'is_the_housing_unit_or_living_habitable',
            'mhpss_experinced',
            'other_mhpss_exp',
            'mhpss_support',
            'other_mhpss_support',
            'community_participation',
            'ce1',
            'prefab_moving',
            'prefab_moving_maybe',
            'prefab_types',
            'other_prefab_types',
            'prefab_pref',
            'ce2',
            'reh_kitchen',
            'reh_bathroom',
            'reh_type',
            'ce3',
            'additional_comments',
            'dm1',
            'dm2',
            'dm3',
            'dm4',
            'dm5',
            'dm6',
            'dm7',
            'dm8',
            'dm9',
            'dm10',
            'dm11',
            'dm12',
            'bl2',
            'bl3',
            'bl4',
            'bl5',
            'co2',
            'co3',
            'co4',
            'co5',
            'co6',
            'co7',
            'co8',
            'co9',
            'co10',
            'fn1',
            'fn2',
            'fn3',
            'fn4',
            'fn5',
            'fn6',
            'fn7',
            'fn8',
            'fn10',
            'fn11',
            'fn12',
            'fn13',
            'fn14',
            'fn15',
            'fn22',
            'fn23',
            'fn24',
            'fn25',
            'fn26',
            'fn16',
            'fn17',
            'fn18',
            'fn19',
            'fn20',
            'fn21',
            'fn27',
            'fn28',
            'fn29',
            'fn30',
            'fn31',
            'al1',
            'al2',
            'al3',
            'al4',
            'al5',
            'al6',
            'al7',
            'al8',
            'al9',
            'al10',
            'wd1',
            'wd3',
            'wd4',
            'wd5',
            'wd6',
            'wd7',
            'wd8',
            'wd9',
            'wd10',
            'wd11',
            'wd12',
            'mt1',
            'mt2',
            'mt3',
            'mt4',
            'mt5',
            'mt6',
            'mt7',
            'mt8',
            'mt9',
            'mt10',
            'mt11',
            'mt12',
            'mt13',
            'mt14',
            'mt15',
            'mt16',
            'mt17',
            'mt19',
            'cm1',
            'cm2',
            'cm3',
            'cm4',
            'cm5',
            'cm6',
            'cm7',
            'cm8',
            'cm9',
            'cm10',
            'cm11',
            'pm1',
            'pm2',
            'pm101',
            'pm18',
            'pm19',
            'pm3',
            'pm4',
            'pm5',
            'pm6',
            'pm7',
            'pm8',
            'pm9',
            'pm10',
            'pm11',
            'pm12',
            'pm13',
            'pm14',
            'pm15',
            'pm16',
            'pm20',
            'pm21',
            'pm22',
            'pm23',
            'pm24',
            'pm25',
            'pm26',
            'pm27',
            'pm28',
            'pm29',
            'pm30',
            'pm31',
            'pm32',
            'pm33',
            'pm34',
            'pm35',
            'pm36',
            'pm37',
            'pm38',
            'pm39',
            'el1',
            'el2',
            'el3',
            'el4',
            'el5',
            'el6',
            'el7',
            'el8',
            'el9',
            'el10',
            'el11',
            'el12',
            'el13',
            'el14',
            'el15',
            'el16',
            'el17',
            'el18',
            'el19',
            'el20',
            'el21',
            'el22',
            'el23',
            'el24',
            'el25',
            'el26',
            'el27',
            'el28',
            'el29',
            'el30',
            'pv_note',
            'pv1',
            'pv2',
            'pv3',
            'pv4',
            'pv5',
            'pv6',
            'pv7',
            'pv8',
            'pv9',
            'pv10',
            'pv11',
            'pv12',
            'item1',
            'quant1',
            'item2',
            'quant2',
            'item3',
            'quant3',
            'item4',
            'quant4',
            'item5',
            'quant5',
            'final_comments',
            'parentglobalid',
            'creationdate',
            'creator',
            'editdate',
            'editor',
            'al11',
            'cm12',
            'cm13',
            'pm40',
            'security_situation_unit',
            'final_comments'
        ]; 

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    // protected $attributes = ['q_9_3_1_first_name', 'q_9_3_2_second_name__father', 'q_9_3_4_last_name'];

    /**
     * @return array<string, string>
     */

    protected $appends = ['full_name'];


    protected function fullName(): Attribute
    {

        return Attribute::make(
            get: fn($value, $attributes) => implode(' ', array_filter([
                $attributes['q_9_3_1_first_name'] ?? '',
                $attributes['q_9_3_2_second_name__father'] ?? '',
                $attributes['q_9_3_4_last_name'] ?? '',
            ])),
        );
    }
    public function building()
    {
        return $this->belongsTo(Building::class, 'parentglobalid', 'globalid'); // Post::class is the related model
    }
    public function buildingStatuses()
    {
        return $this->hasMany(BuildingStatus::class);
    }

    public function engineerStatus()
    {
        return $this->hasOne(HousingStatus::class, 'housing_id', 'objectid')
            ->where('type', 'QC/QA Engineer');
    }
    public function finalApproval()
    {
        return $this->hasOne(HousingStatus::class, 'housing_id', 'objectid')
            ->where('status_id', 19);
    }
    public function lawyerStatus()
    {
        return $this->hasOne(HousingStatus::class, 'housing_id', 'objectid')
            ->where('type', 'Legal Auditor');
    }

    public function statusByType($type)
    {
        return $this->hasOne(HousingStatus::class, 'housing_id', 'objectid')
            ->where('type', $type);
    }
}
