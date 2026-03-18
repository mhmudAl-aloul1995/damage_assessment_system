<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        /*
        |--------------------------------------------------------------------------
        | Areas
        |--------------------------------------------------------------------------
        */

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('field_val_en')->nullable();
            $table->string('field_val_ar')->nullable();
        });

        /*
        |--------------------------------------------------------------------------
        | Assessments
        |--------------------------------------------------------------------------
        */

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('label')->nullable();
            $table->string('hint')->nullable();
        });

        /*
        |--------------------------------------------------------------------------
        | Buildings
        |--------------------------------------------------------------------------
        */

        Schema::create('buildings', function (Blueprint $table) {
            $table->id(); // int(11) primary key

            // Survey Identification
            $table->integer('objectid')->unique();
            $table->text('globalid')->unique();
            $table->text('field_status')->nullable();
            $table->text('building_committee_status')->nullable();
            $table->text('unit_committee_status')->nullable();
            $table->text('unit_committee_count')->nullable();

            // Location & Administrative
            $table->text('parcel_no1')->nullable();
            $table->text('block_no1')->nullable();
            $table->text('owner_na')->nullable();
            $table->text('units_count')->nullable();
            $table->text('assignedto')->nullable();
            $table->text('groupnumber')->nullable();
            $table->text('zone_code')->nullable();
            $table->text('building_address')->nullable();

            // Metadata & Device Info
            $table->text('start')->nullable();
            $table->text('end')->nullable();
            $table->text('today')->nullable();
            $table->text('username')->nullable();
            $table->text('simserial')->nullable();
            $table->text('subscriberid')->nullable();
            $table->text('deviceid')->nullable();
            $table->text('phonenumber')->nullable();
            $table->dateTime('submission_date')->nullable();

            // Custom Notes
            foreach (range(1, 9) as $i) {
                $table->text("note0$i")->nullable();
            }

            // Building Technical Details
            $table->text('weather')->nullable();
            $table->text('security_situation')->nullable();
            $table->text('building_damage_status')->nullable();
            $table->text('building_type')->nullable();
            $table->text('building_type_other')->nullable();
            $table->text('building_use')->nullable();
            $table->text('building_name')->nullable();
            $table->text('date_of_damage')->nullable();
            $table->text('building_material')->nullable();
            $table->text('other_material')->nullable();
            $table->text('building_age')->nullable();
            $table->text('floor_nos')->nullable();
            $table->text('ground_floor_area__m2')->nullable();
            $table->text('floor_area_m2')->nullable();
            $table->text('elevator_damaged_doors')->nullable();
            $table->text('is_risk_parts')->nullable();

            // Unit Statistics
            $table->text('units_nos')->nullable();
            $table->text('damaged_units_nos')->nullable();
            $table->text('occupied_units_nos')->nullable();
            $table->text('vacant_units_nos')->nullable();
            $table->text('floor_nos_1')->nullable();

            // Hazards & Debris
            $table->text('is_damaged_before')->nullable();
            $table->text('if_damaged')->nullable();
            $table->text('building_debris_exist')->nullable();
            $table->text('building_debris_qty')->nullable();
            $table->text('building_debris_blocking')->nullable();
            $table->text('uxo_present')->nullable();
            $table->text('bodies_present')->nullable();
            $table->text('estimated_number_of_bodies')->nullable();
            $table->text('building_status_visit')->nullable();

            // Roofing
            $table->text('building_roof_type')->nullable();
            $table->text('clay_tile_area')->nullable();
            $table->text('concrete_area')->nullable();
            $table->text('aspestos_area')->nullable();
            $table->text('scorite_area')->nullable();
            $table->text('other_roof')->nullable();
            $table->text('other_roof_area')->nullable();

            // Legal & Ownership
            $table->text('building_ownership')->nullable();
            $table->text('owner_status')->nullable();
            $table->text('building_responsible')->nullable();
            $table->text('building_authorization')->nullable();
            $table->text('land_fully_owned')->nullable();
            $table->text('owner_name')->nullable();
            $table->text('owner_id')->nullable();
            $table->text('owner_mobile')->nullable();
            $table->text('owner_mobile_1')->nullable();
            $table->text('owner_mobile_v_1')->nullable();
            $table->text('owner_name_1')->nullable();

            // Boards
            $table->text('board1_name')->nullable();
            $table->text('board1_id')->nullable();
            $table->text('board1_number')->nullable();
            $table->text('board2_name')->nullable();
            $table->text('board2_id')->nullable();
            $table->text('board2_number')->nullable();

            // Rental & Documentation
            $table->text('has_authorization_if_not_owner')->nullable();
            $table->text('authorization_details')->nullable();
            $table->text('is_rented')->nullable();
            $table->text('tenant_names')->nullable();
            $table->text('agreement_type')->nullable();
            $table->text('agreement_duration')->nullable();
            $table->text('has_documents')->nullable();
            $table->text('doc_types_available')->nullable();
            $table->text('doc_types_other')->nullable();
            $table->text('no_documents_reason')->nullable();
            $table->text('need_renew_docs')->nullable();
            $table->text('doc_challenges')->nullable();
            $table->text('doc_challenges_other')->nullable();

            // Disputes & Notes
            $table->text('has_dispute')->nullable();
            $table->text('dispute_types')->nullable();
            $table->text('dispute_other')->nullable();
            $table->text('general_notes')->nullable();
            $table->text('attach_one_photo_for_each_of_the_following_documents')->nullable();
            $table->text('select_document')->nullable();

            // Building Services
            $table->text('has_elevator')->nullable();
            $table->text('elevator_number')->nullable();
            $table->text('elevator_status')->nullable();
            $table->text('elevator_box')->nullable();
            $table->text('elevator_motor')->nullable();
            $table->text('has_solar')->nullable();
            $table->text('solar_damage_status')->nullable();
            $table->text('has_well')->nullable();
            $table->text('well_damage_status')->nullable();
            $table->text('has_fence')->nullable();
            $table->text('fence_damage_status')->nullable();
            $table->text('fence_length')->nullable();
            $table->text('has_electric_room')->nullable();
            $table->text('electric_room_damage_status')->nullable();
            $table->text('has_sewage')->nullable();
            $table->text('sewage_damage_status')->nullable();
            $table->text('has_other_service')->nullable();
            $table->text('other_service_details')->nullable();
            $table->text('building_services_notes')->nullable();

            // Structural Features
            $table->text('staircase_status')->nullable();
            $table->text('staircase_widt')->nullable();
            $table->text('has_parking')->nullable();
            $table->text('parking_status')->nullable();
            $table->text('garage_area')->nullable();
            $table->text('garage_type')->nullable();
            $table->text('has_canopy')->nullable();
            $table->text('canopy_status')->nullable();
            $table->text('carport_length')->nullable();
            $table->text('carport_width')->nullable();
            $table->text('carport_area')->nullable();
            $table->text('carport_height')->nullable();
            $table->text('has_basement')->nullable();
            $table->text('basement_status')->nullable();
            $table->text('basement_area')->nullable();
            $table->text('has_mezzanine')->nullable();
            $table->text('mezzanine_status')->nullable();
            $table->text('roof_terrace_area')->nullable();

            // Geo & Meta
            $table->text('comments_recommendations')->nullable();
            $table->text('break01_note')->nullable();
            $table->double('shape__area')->nullable();
            $table->double('shape__length')->nullable();
            $table->datetime('creationdate')->nullable();
            $table->text('creator')->nullable();
            $table->datetime('editdate')->nullable();
            $table->text('editor')->nullable();
            $table->text('security_info')->nullable();
            $table->text('is_draft')->nullable();
            $table->text('service_ownership')->nullable();
            $table->text('service_ownership_name')->nullable();
            $table->text('land_area')->nullable();
            $table->text('governorate')->nullable();
            $table->text('municipalitie')->nullable();
            $table->text('neighborhood')->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Housing Units
        |--------------------------------------------------------------------------
        */

        Schema::create('housing_units', function (Blueprint $table) {
            $table->id();
            $table->integer('objectid')->unique();
            $table->text('globalid');
            $table->text('housing_unit_type')->nullable();
            $table->text('unit_damage_status')->nullable();
            $table->text('floor_number')->nullable();
            $table->text('housing_unit_number')->nullable();
            $table->text('unit_direction')->nullable();
            $table->string('damaged_area_m2', 250)->nullable();
            $table->text('infra_type2')->nullable();
            $table->text('house_unit_ownership')->nullable();
            $table->text('other_ownership')->nullable();
            $table->text('occupied')->nullable();
            $table->string('number_of_rooms', 250)->nullable();
            $table->text('identity_type1')->nullable();
            $table->string('id_number1', 255)->nullable();
            $table->text('passport1')->nullable();
            $table->text('other_id1')->nullable();
            $table->text('unit_owner')->nullable();
            $table->text('agreement_duration')->nullable();
            $table->text('q_9_3_1_first_name')->nullable();
            $table->text('q_9_3_2_second_name__father')->nullable();
            $table->text('q_9_3_3_third_name__grandfather')->nullable();
            $table->text('q_9_3_4_last_name')->nullable();
            $table->text('sex')->nullable();
            $table->string('mobile_number', 250)->nullable();
            $table->string('additional_mobile', 250)->nullable();
            $table->text('owner_job')->nullable();
            $table->text('other_job')->nullable();
            $table->string('age', 250)->nullable();
            $table->text('marital_status')->nullable();
            $table->text('empty_land_rhu')->nullable();
            $table->text('no_spouses')->nullable();
            $table->text('spouse1')->nullable();
            $table->string('spouse1_id', 250)->nullable();
            $table->text('spouse2')->nullable();
            $table->text('spouse2_id')->nullable();
            $table->text('spouse3')->nullable();
            $table->text('spouse3_id')->nullable();
            $table->text('spouse4')->nullable();
            $table->text('spouse4_id')->nullable();
            $table->text('are_there_people_with_disability')->nullable();
            $table->text('number_of_people_with_disability')->nullable();
            $table->text('handicapped_type')->nullable();
            $table->text('other_handicapped')->nullable();
            $table->text('is_refugee')->nullable();
            $table->text('unrwa_registration_number')->nullable();
            $table->text('number_of_nuclear_families')->nullable();
            $table->text('mchildren_001')->nullable();
            $table->text('myoung')->nullable();
            $table->text('melderly')->nullable();
            $table->text('fchildren')->nullable();
            $table->text('fyoung_001')->nullable();
            $table->text('felderly')->nullable();
            $table->text('pregnant')->nullable();
            $table->text('lactating')->nullable();
            $table->text('the_unit_resident')->nullable();
            $table->text('current_address')->nullable();
            $table->text('current_residence')->nullable();
            $table->text('current_residence_other')->nullable();
            $table->text('shelter_name')->nullable();
            $table->text('shelter_type')->nullable();
            $table->text('shelter_type_other')->nullable();
            $table->text('governorate')->nullable();
            $table->text('locality')->nullable();
            $table->text('neighborhood')->nullable();
            $table->text('street')->nullable();
            $table->text('closest_facility2')->nullable();
            $table->text('identity_type2')->nullable();
            $table->text('rentee_id_passport_number')->nullable();
            $table->text('rentee_resident_full_name')->nullable();
            $table->text('q_13_3_1_first_name')->nullable();
            $table->text('q_13_3_2_second_name__father')->nullable();
            $table->text('q_13_3_3_third_name__grandfather')->nullable();
            $table->text('q_13_3_4_last_name__family')->nullable();
            $table->text('rentee_mobile_number')->nullable();
            $table->text('work_type')->nullable();
            $table->text('other_work')->nullable();
            $table->text('land_location_details')->nullable();
            $table->text('external_finishing_of_the_unit')->nullable();
            $table->text('other_external_finishing')->nullable();
            $table->text('is_finished')->nullable();
            $table->text('finishing_extent')->nullable();
            $table->text('internal_finishing_of_the_unit')->nullable();
            $table->text('finishing_partial_types')->nullable();
            $table->text('has_fire')->nullable();
            $table->text('fire_extent')->nullable();
            $table->text('fire_severity')->nullable();
            $table->text('fire_locations')->nullable();
            $table->text('fire_rooms_count')->nullable();
            $table->text('fire_area')->nullable();
            $table->text('furniture_ownership')->nullable();
                        $table->text('tenant_name')->nullable();
            $table->string('percentage_of_damaged_furniture', 250)->nullable();
            $table->text('unit_stripping')->nullable();
            $table->text('unit_stripping_details')->nullable();
            $table->text('stripping_area')->nullable();
            $table->text('stripping_locations')->nullable();
            $table->text('rubble_removal_is_needed')->nullable();
            $table->text('activation_of_uxo_ha_d_material_clearance')->nullable();
            $table->text('unit_support_needed')->nullable();
            $table->text('is_the_housing_unit_or_living_habitable')->nullable();
            $table->text('mhpss_experinced')->nullable();
            $table->text('other_mhpss_exp')->nullable();
            $table->text('mhpss_support')->nullable();
            $table->text('other_mhpss_support')->nullable();
            $table->text('community_participation')->nullable();
            $table->text('ce1')->nullable();
            $table->text('prefab_moving')->nullable();
            $table->text('prefab_moving_maybe')->nullable();
            $table->text('prefab_types')->nullable();
            $table->text('other_prefab_types')->nullable();
            $table->text('prefab_pref')->nullable();
            $table->text('ce2')->nullable();
            $table->text('reh_kitchen')->nullable();
            $table->text('reh_bathroom')->nullable();
            $table->text('reh_type')->nullable();
            $table->text('ce3')->nullable();
            $table->text('additional_comments')->nullable();
            $table->text('dm1')->nullable();
            $table->text('dm2')->nullable();
            $table->text('dm3')->nullable();
            $table->text('dm4')->nullable();
            $table->text('dm5')->nullable();
            $table->text('dm6')->nullable();
            $table->text('dm7')->nullable();
            $table->text('dm8')->nullable();
            $table->text('dm9')->nullable();
            $table->text('dm10')->nullable();
            $table->text('dm11')->nullable();
            $table->text('dm12')->nullable();
            $table->text('bl2')->nullable();
            $table->text('bl3')->nullable();
            $table->text('bl4')->nullable();
            $table->text('bl5')->nullable();
            $table->text('co2')->nullable();
            $table->text('co3')->nullable();
            $table->text('co4')->nullable();
            $table->text('co5')->nullable();
            $table->text('co6')->nullable();
            $table->text('co7')->nullable();
            $table->text('co8')->nullable();
            $table->text('co9')->nullable();
            $table->text('co10')->nullable();
            $table->string('fn1', 250)->nullable();
            $table->text('fn2')->nullable();
            $table->text('fn3')->nullable();
            $table->text('fn4')->nullable();
            $table->text('fn5')->nullable();
            $table->text('fn6')->nullable();
            $table->text('fn7')->nullable();
            $table->text('fn8')->nullable();
            $table->text('fn10')->nullable();
            $table->text('fn11')->nullable();
            $table->text('fn12')->nullable();
            $table->text('fn13')->nullable();
            $table->text('fn14')->nullable();
            $table->text('fn15')->nullable();
            $table->text('fn16')->nullable();
            $table->text('fn17')->nullable();
            $table->text('fn18')->nullable();
            $table->text('fn19')->nullable();
            $table->text('fn20')->nullable();
            $table->text('fn21')->nullable();
            $table->text('fn22')->nullable();
            $table->text('fn23')->nullable();
            $table->text('fn24')->nullable();
            $table->text('fn25')->nullable();
            $table->text('fn26')->nullable();
            $table->text('fn27')->nullable();
            $table->text('fn28')->nullable();
            $table->text('fn29')->nullable();
            $table->text('fn30')->nullable();
            $table->text('fn31')->nullable();
            $table->text('al1')->nullable();
            $table->text('al2')->nullable();
            $table->text('al3')->nullable();
            $table->text('al4')->nullable();
            $table->text('al5')->nullable();
            $table->text('al6')->nullable();
            $table->text('al7')->nullable();
            $table->text('al8')->nullable();
            $table->text('al9')->nullable();
            $table->text('al10')->nullable();
            $table->text('wd1')->nullable();
            $table->text('wd3')->nullable();
            $table->text('wd4')->nullable();
            $table->text('wd5')->nullable();
            $table->text('wd6')->nullable();
            $table->text('wd7')->nullable();
            $table->text('wd8')->nullable();
            $table->text('wd9')->nullable();
            $table->text('wd10')->nullable();
            $table->text('wd11')->nullable();
            $table->text('wd12')->nullable();
            $table->text('mt1')->nullable();
            $table->text('mt2')->nullable();
            $table->text('mt3')->nullable();
            $table->text('mt4')->nullable();
            $table->text('mt5')->nullable();
            $table->text('mt6')->nullable();
            $table->text('mt7')->nullable();
            $table->text('mt8')->nullable();
            $table->text('mt9')->nullable();
            $table->text('mt10')->nullable();
            $table->text('mt11')->nullable();
            $table->text('mt12')->nullable();
            $table->text('mt13')->nullable();
            $table->text('mt14')->nullable();
            $table->text('mt15')->nullable();
            $table->text('mt16')->nullable();
            $table->text('mt17')->nullable();
            $table->text('mt19')->nullable();
            $table->text('cm1')->nullable();
            $table->text('cm2')->nullable();
            $table->text('cm3')->nullable();
            $table->text('cm4')->nullable();
            $table->text('cm5')->nullable();
            $table->text('cm6')->nullable();
            $table->text('cm7')->nullable();
            $table->text('cm8')->nullable();
            $table->text('cm9')->nullable();
            $table->text('cm10')->nullable();
            $table->text('cm11')->nullable();
            $table->text('pm1')->nullable();
            $table->text('pm2')->nullable();
            $table->text('pm101')->nullable();
            $table->text('pm18')->nullable();
            $table->text('pm19')->nullable();
            $table->text('pm3')->nullable();
            $table->text('pm4')->nullable();
            $table->text('pm5')->nullable();
            $table->text('pm6')->nullable();
            $table->text('pm7')->nullable();
            $table->text('pm8')->nullable();
            $table->text('pm9')->nullable();
            $table->text('pm10')->nullable();
            $table->text('pm11')->nullable();
            $table->text('pm12')->nullable();
            $table->text('pm13')->nullable();
            $table->text('pm14')->nullable();
            $table->text('pm15')->nullable();
            $table->text('pm16')->nullable();
            $table->text('pm20')->nullable();
            $table->text('pm21')->nullable();
            $table->text('pm22')->nullable();
            $table->text('pm23')->nullable();
            $table->text('pm24')->nullable();
            $table->text('pm25')->nullable();
            $table->text('pm26')->nullable();
            $table->text('pm27')->nullable();
            $table->text('pm28')->nullable();
            $table->text('pm29')->nullable();
            $table->text('pm30')->nullable();
            $table->text('pm31')->nullable();
            $table->text('pm32')->nullable();
            $table->text('pm33')->nullable();
            $table->text('pm34')->nullable();
            $table->text('pm35')->nullable();
            $table->text('pm36')->nullable();
            $table->text('pm37')->nullable();
            $table->text('pm38')->nullable();
            $table->text('pm39')->nullable();
            $table->text('el1')->nullable();
            $table->text('el2')->nullable();
            $table->text('el3')->nullable();
            $table->text('el4')->nullable();
            $table->text('el5')->nullable();
            $table->text('el6')->nullable();
            $table->text('el7')->nullable();
            $table->text('el8')->nullable();
            $table->text('el9')->nullable();
            $table->text('el10')->nullable();
            $table->text('el11')->nullable();
            $table->text('el12')->nullable();
            $table->text('el13')->nullable();
            $table->text('el14')->nullable();
            $table->text('el15')->nullable();
            $table->text('el16')->nullable();
            $table->text('el17')->nullable();
            $table->text('el18')->nullable();
            $table->text('el19')->nullable();
            $table->text('el20')->nullable();
            $table->text('el21')->nullable();
            $table->text('el22')->nullable();
            $table->text('el23')->nullable();
            $table->text('el24')->nullable();
            $table->text('el25')->nullable();
            $table->text('el26')->nullable();
            $table->text('el27')->nullable();
            $table->text('el28')->nullable();
            $table->text('el29')->nullable();
            $table->text('el30')->nullable();
            $table->text('pv_note')->nullable();
            $table->text('pv1')->nullable();
            $table->text('pv2')->nullable();
            $table->text('pv3')->nullable();
            $table->text('pv4')->nullable();
            $table->text('pv5')->nullable();
            $table->text('pv6')->nullable();
            $table->text('pv7')->nullable();
            $table->text('pv8')->nullable();
            $table->text('pv9')->nullable();
            $table->text('pv10')->nullable();
            $table->text('pv11')->nullable();
            $table->text('pv12')->nullable();
            $table->text('item1')->nullable();
            $table->text('quant1')->nullable();
            $table->text('item2')->nullable();
            $table->text('quant2')->nullable();
            $table->text('item3')->nullable();
            $table->text('quant3')->nullable();
            $table->text('item4')->nullable();
            $table->text('quant4')->nullable();
            $table->text('item5')->nullable();
            $table->text('quant5')->nullable();
            $table->text('final_comments')->nullable();
            $table->text('parentglobalid')->nullable();
            $table->datetime('creationdate')->nullable();
            $table->text('creator')->nullable();
            $table->datetime('editdate')->nullable();
            $table->text('editor')->nullable();
            $table->text('al11')->nullable();
            $table->text('cm12')->nullable();
            $table->text('cm13')->nullable();
            $table->text('pm40')->nullable();
            $table->text('security_situation_unit')->nullable();
            $table->text('security_unit_info')->nullable();
            $table->text('cm14')->nullable();
            $table->text('cm15')->nullable();
            $table->text('cm16')->nullable();
            $table->timestamps();
        });



        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        Schema::create('filters', function (Blueprint $table) {

            $table->id();
            $table->string('list_name')->nullable();
            $table->string('name')->nullable();
            $table->string('label')->nullable();
        });

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        Schema::create('users', function (Blueprint $table) {

            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('avatar')->nullable();

            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            $table->string('password');
            $table->rememberToken();

            $table->timestamps();
        });
    }

    public function down(): void
    {

        Schema::dropIfExists('housing_units');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('filters');
        Schema::dropIfExists('users');
    }
};
