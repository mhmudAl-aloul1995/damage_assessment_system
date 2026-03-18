<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use App\Models\HousingUnit;

/**
 * Class Building
 *
 * @property int $id
 * @property int|null $objectid
 * @property string|null $globalid
 * @property string|null $field_status
 * @property string|null $building_committee_status
 * @property string|null $unit_committee_status
 * @property string|null $unit_committee_count
 * @property int|null $parcel_no1
 * @property int|null $block_no1
 * @property string|null $owner_na
 * @property string|null $units_count
 * @property string|null $assignedto
 * @property string|null $groupnumber
 * @property string|null $zone_code
 * @property string|null $start
 * @property string|null $end
 * @property string|null $today
 * @property string|null $username
 * @property string|null $simserial
 * @property string|null $subscriberid
 * @property string|null $deviceid
 * @property string|null $phonenumber
 * @property string|null $note01
 * @property string|null $note02
 * @property string|null $note03
 * @property string|null $note04
 * @property string|null $note05
 * @property string|null $note06
 * @property string|null $note07
 * @property string|null $note08
 * @property string|null $note09
 * @property string|null $weather
 * @property string|null $security_situation
 * @property string|null $building_damage_status
 * @property string|null $building_type
 * @property string|null $building_type_other
 * @property string|null $building_use
 * @property string|null $building_name
 * @property string|null $date_of_damage
 * @property string|null $building_material
 * @property string|null $other_material
 * @property string|null $building_age
 * @property int|null $floor_nos
 * @property int|null $ground_floor_area__m2
 * @property int|null $floor_area_m2
 * @property int|null $units_nos
 * @property int|null $damaged_units_nos
 * @property int|null $occupied_units_nos
 * @property string|null $vacant_units_nos
 * @property string|null $is_damaged_before
 * @property string|null $if_damaged
 * @property string|null $building_debris_exist
 * @property string|null $building_debris_qty
 * @property string|null $building_debris_blocking
 * @property string|null $uxo_present
 * @property string|null $bodies_present
 * @property string|null $estimated_number_of_bodies
 * @property string|null $building_status_visit
 * @property string|null $building_roof_type
 * @property string|null $clay_tile_area
 * @property int|null $concrete_area
 * @property string|null $aspestos_area
 * @property string|null $scorite_area
 * @property string|null $other_roof
 * @property string|null $other_roof_area
 * @property string|null $building_ownership
 * @property string|null $owner_status
 * @property string|null $building_responsible
 * @property string|null $building_authorization
 * @property string|null $land_fully_owned
 * @property string|null $owner_name
 * @property string|null $owner_id
 * @property string|null $owner_mobile
 * @property string|null $board1_name
 * @property string|null $board1_id
 * @property string|null $board1_number
 * @property string|null $board2_name
 * @property string|null $board2_id
 * @property string|null $board2_number
 * @property string|null $has_authorization_if_not_owner
 * @property string|null $authorization_details
 * @property string|null $is_rented
 * @property string|null $tenant_names
 * @property string|null $agreement_type
 * @property string|null $agreement_duration
 * @property string|null $has_documents
 * @property string|null $doc_types_available
 * @property string|null $doc_types_other
 * @property string|null $no_documents_reason
 * @property string|null $need_renew_docs
 * @property string|null $doc_challenges
 * @property string|null $doc_challenges_other
 * @property string|null $has_dispute
 * @property string|null $dispute_types
 * @property string|null $dispute_other
 * @property string|null $general_notes
 * @property string|null $attach_one_photo_for_each_of_the_following_documents
 * @property string|null $select_document
 * @property string|null $has_elevator
 * @property string|null $elevator_number
 * @property string|null $elevator_status
 * @property string|null $elevator_box
 * @property string|null $elevator_motor
 * @property string|null $has_solar
 * @property string|null $solar_damage_status
 * @property string|null $has_well
 * @property string|null $well_damage_status
 * @property string|null $has_fence
 * @property string|null $fence_damage_status
 * @property string|null $fence_length
 * @property string|null $has_electric_room
 * @property string|null $electric_room_damage_status
 * @property string|null $has_sewage
 * @property string|null $sewage_damage_status
 * @property string|null $has_other_service
 * @property string|null $other_service_details
 * @property string|null $building_services_notes
 * @property string|null $staircase_status
 * @property string|null $staircase_widt
 * @property string|null $has_parking
 * @property string|null $parking_status
 * @property string|null $garage_area
 * @property string|null $garage_type
 * @property string|null $has_canopy
 * @property string|null $canopy_status
 * @property string|null $carport_length
 * @property string|null $carport_width
 * @property string|null $carport_area
 * @property string|null $carport_height
 * @property string|null $has_basement
 * @property string|null $basement_status
 * @property string|null $basement_area
 * @property string|null $has_mezzanine
 * @property string|null $mezzanine_status
 * @property string|null $roof_terrace_area
 * @property string|null $comments_recommendations
 * @property string|null $break01_note
 * @property float|null $shape__area
 * @property float|null $shape__length
 * @property string|null $creationdate
 * @property string|null $creator
 * @property string|null $editdate
 * @property string|null $editor
 * @property string|null $security_info
 * @property string|null $is_draft
 * @property string|null $service_ownership
 * @property string|null $service_ownership_name
 * @property string|null $land_area
 * @property string|null $governorate
 * @property string|null $municipalitie
 * @property string|null $neighborhood
 */
class Building extends Model
{
    protected $table = 'buildings';

    /**
     * @var string
     */
    protected $connection = 'mysql';

    protected $primaryKey = 'id';

    public $timestamps = false;



    /* 
        protected function dateOfDamage(): Attribute
        {
            return Attribute::make(
                get: function ($value) {

                    if (is_numeric($value) == true) {




                    }

                    return $value;
                },
            );
        } */
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'assignedto',
        'objectid',
        'globalid',
        'field_status',
        'building_committee_status',
        'unit_committee_status',
        'unit_committee_count',
        'parcel_no1',
        'block_no1',
        'owner_na',
        'units_count',
        'groupnumber',
        'zone_code',
        'start',
        'end',
        'today',
        'username',
        'simserial',
        'subscriberid',
        'deviceid',
        'phonenumber',
        'note01',
        'note02',
        'note03',
        'note04',
        'note05',
        'note06',
        'note07',
        'note08',
        'note09',
        'weather',
        'security_situation',
        'building_damage_status',
        'building_type',
        'building_type_other',
        'building_use',
        'building_name',
        'date_of_damage',
        'building_material',
        'other_material',
        'building_age',
        'floor_nos',
        'ground_floor_area__m2',
        'floor_area_m2',
        'units_nos',
        'damaged_units_nos',
        'occupied_units_nos',
        'vacant_units_nos',
        'is_damaged_before',
        'if_damaged',
        'building_debris_exist',
        'building_debris_qty',
        'building_debris_blocking',
        'uxo_present',
        'bodies_present',
        'estimated_number_of_bodies',
        'building_status_visit',
        'building_roof_type',
        'clay_tile_area',
        'concrete_area',
        'aspestos_area',
        'scorite_area',
        'other_roof',
        'other_roof_area',
        'building_ownership',
        'owner_status',
        'building_responsible',
        'building_authorization',
        'land_fully_owned',
        'owner_name',
        'owner_id',
        'owner_mobile',
        'board1_name',
        'board1_id',
        'board1_number',
        'board2_name',
        'board2_id',
        'board2_number',
        'has_authorization_if_not_owner',
        'authorization_details',
        'is_rented',
        'tenant_names',
        'agreement_type',
        'agreement_duration',
        'has_documents',
        'doc_types_available',
        'doc_types_other',
        'no_documents_reason',
        'need_renew_docs',
        'doc_challenges',
        'doc_challenges_other',
        'has_dispute',
        'dispute_types',
        'dispute_other',
        'general_notes',
        'attach_one_photo_for_each_of_the_following_documents',
        'select_document',
        'has_elevator',
        'elevator_number',
        'elevator_status',
        'elevator_box',
        'elevator_motor',
        'has_solar',
        'solar_damage_status',
        'has_well',
        'well_damage_status',
        'has_fence',
        'fence_damage_status',
        'fence_length',
        'has_electric_room',
        'electric_room_damage_status',
        'has_sewage',
        'sewage_damage_status',
        'has_other_service',
        'other_service_details',
        'building_services_notes',
        'staircase_status',
        'staircase_widt',
        'has_parking',
        'parking_status',
        'garage_area',
        'garage_type',
        'has_canopy',
        'canopy_status',
        'carport_length',
        'carport_width',
        'carport_area',
        'carport_height',
        'has_basement',
        'basement_status',
        'basement_area',
        'has_mezzanine',
        'mezzanine_status',
        'roof_terrace_area',
        'comments_recommendations',
        'break01_note',
        'shape__area',
        'shape__length',
        'creationdate',
        'creator',
        'editdate',
        'editor',
        'security_info',
        'is_draft',
        'service_ownership',
        'service_ownership_name',
        'land_area',
        'governorate',
        'municipalitie',
        'neighborhood',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [];

    /**
     * @return array<string, string>
     */

    public function housing_unit()
    {
        return $this->hasMany(HousingUnit::class, 'parentglobalid', 'globalid');
    }
    public function buildingStatuses()
    {
        return $this->hasMany(BuildingStatus::class);
    }

    public function engineerStatus()
    {
        return $this->hasOne(BuildingStatus::class)
            ->where('type', 'eng');
    }

    public function lawyerStatus()
    {
        return $this->hasOne(BuildingStatus::class)
            ->where('type', 'lawyer');
    }
    public function assignedUsers()
{
    return $this->hasMany(AssignedAssessmentUser::class);
}
}
