<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Exports\TableExport;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Buildings;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Project;
use Yajra\Datatables\Datatables;
use Rap2hpoutre\FastExcel\FastExcel;
use Yajra\Datatables\Enginges\EloquentEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Database\Eloquent\Builder;
use Hash;
use Spatie\Permission\Models\Role;
use App\Models\HousingUnit;
use App\Models\Building;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use function Spatie\LaravelPdf\Support\pdf;
use App\Models\Filter;
use App\Models\User;
use App\Models\AssignedAssessmentUser;
use App\Models\BuildingStatusHistory;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use App\Models\HousingStatusHistory;
use App\Models\AssessmentStatus;

class auditController extends Controller
{
    public function index(Request $request)
    {

        if ($request->ajax()) {
            $globalids = [
                '51ea2320-1c6b-4115-af83-e8103cb335c0',
                '6480b68c-c0c7-4411-9f5d-3060cf30725c',
                '21f0fa33-e4cf-4713-a585-c5c17902cd43',
                '30139479-0413-4190-a7e3-054f91af2609',
                '084c000a-c0bd-4eed-9a01-3dd491bc1eff',
                'cebb51d2-d7ff-4c1d-a18a-f71a85732f8b',
                '417d1d0a-9346-4ca7-b90e-a55b38c7d34a',
                '16f42e59-4e00-41bd-a9e6-bd31ddf5ca9c',
                'af285907-a27f-41e8-93f3-b8f561602b7c',
                '7346e9e2-63b4-4b28-be8d-1e66b6aa61c8',
                '16c080fe-49e5-490e-b561-0cfbb4901da6',
                'ce60ffa3-a793-412f-8a96-1491b0279028',
                'be8f867e-479b-444c-b4fc-daec0a6020f4',
                '81d1f925-e92a-4a91-add6-d7dfe56b59ff',
                'ab5205a0-32a0-4136-a07b-033a59c7b513',
                '11281d69-8c6b-41fe-a049-f0d6a98f3822',
                '59f1d150-1010-4921-b27e-51855b99808c',
                'b92b1413-008b-4ae9-8108-72fb50e4aa8c',
                '376def22-b3f7-4fce-8e93-3c1ff6f2f0b1',
                '30f77efb-d2b9-47ce-aa00-1290c650e564',
                'c4658a68-f4f0-4240-8327-c3a095facca2',
                'd320f565-a7cc-4ac5-82c3-eda528f7ad31',
                '584e6f17-7197-4b71-947f-6923f31f6d52',
                '783c28e9-d330-416e-a88e-80c0bf6a89a9',
                '72d4c290-1def-4ab6-b4f7-ccfa8d87ba0a',
                '14da7bd9-7ffd-4f87-9ea3-608485a2e84c',
                'a2340e99-4cf5-4c83-a51c-90c42695ff79',
                '87047c0e-ae52-4726-ab6d-3b4f72a56793',
                '9ec810f4-1426-4301-947f-67e267182613',
                'edb43ce9-0de4-4874-8556-4aa6b5f562e0',
                'b5190b28-35ea-45d3-9bcd-619c2ec196c9',
                '02c1078f-8a3d-49b4-b251-8ec3d9f9417b',
                '446d5c77-81ca-473c-8457-8f912c4847b7',
                'd5ed0eba-d8cc-4c7a-8578-56e253d13122',
                '611f3ef3-676b-4243-acfa-94f0b919b3e7',
                'bd135790-6e4d-4807-afd5-7459dc9a8684',
                'c3cff6c0-82e5-4c51-9459-87a9ec07508f',
                '4404340c-9f44-48a7-9a0d-52603abdeae1',
                '80cb29e3-9e87-4ad7-90c8-bae905cb6416',
                'b57195b4-4bcd-4596-8e50-a6b8f1df54be',
                '397cbd8c-b142-403e-9f2d-8cf342e48136',
                '7e6d69c2-daa6-4e2a-8706-8e9e50bb9051',
                'd881e29b-1caa-4159-b408-d337ccc9efb0',
                '2971edfb-7455-4f55-b578-c4d9f186a99c',
                'ccc48f6f-8ec6-491c-be1d-6d58af65bc6d',
                'a2f3c92e-1ff6-4c0d-ae1c-7b36706cf60a',
                'a0e6acd4-f5a1-4b96-aefb-f21781e8f597',
                'bdf5a606-2add-403c-8ec7-4faa92fe522a',
                '615bc3fa-214f-4658-b1a2-65ded1d4ad68',
                '3c0f2cbf-9b53-4010-9810-dc8adebf976c',
                '3c9bb766-04f5-4d5d-bbc4-0ee2de365804',
                '28e7e917-834a-4a98-ae95-5cee7417b0af',
                '57849ddd-32f9-4a11-90f0-cfd3dacfbbef',
                '760f3b06-39fb-48bf-b389-d568f2f5cb47',
                '6a3835e8-3683-46d5-8844-55d6bfb0061e',
                'a3a23aa3-882b-429b-ac72-c6457c9cacd9',
                'e0ebfd59-edd8-459e-9b04-e011cb4b1f6c',
                '018e2bc7-efab-4b0a-a359-6f1378bb8bd9',
                '33a6d253-16fa-4c27-bab4-a98a8d1d1fef',
                '622c67bf-2830-48f4-b953-047c3aad6615',
                'a381316c-03ba-4737-9a00-dac6d4406735',
                '73d0141f-0ae6-4a50-8d6f-2d2d560d4a0b',
                '2076da8b-38a6-48e7-a5ff-039e116ef29d',
                '3c526ea4-73c2-4f4d-9f7b-f96c1c96537b',
                '29860fc4-0c89-462e-9bd7-cb75004a3857',
                'b660cf75-1e96-41b6-847a-b254e0c02da3',
                'b05c4a1c-9350-4edd-b3e1-6b3f0a4ae545',
                '45c35a40-0a1c-4000-8eef-a135eb37e42a',
                '175bdda6-ad90-498f-ae80-522022bd0c79',
                'b77bc54d-05fe-43ca-8d54-f20298c41e9f',
                '318f0378-ce5d-4f5f-9212-082137f043ce',
                'e374e1c5-9c5a-49d7-be15-44e469ed62b5',
                'd15ea312-b3db-4069-af08-2cf31379fe19',
                '84df5928-b2dc-4158-97f0-b3d3e0ef76e9',
                '0e650492-109f-465f-aec9-b85a00e777b3',
                '07a4e116-e96c-4210-8f86-ea02b0102b45',
                '5f9d368d-4f86-4fa8-b7bd-063339341203',
                '9a455983-2bc7-4321-ae39-315a328eeaba',
                '126d3cbe-d062-46cd-a32b-89a96d7b2c30',
                'd5d083ec-3e96-431a-9417-d2a474c7a6e9',
                'c5553442-767d-4824-8f02-0c9f8733eebf',
                'b08a4bcf-19df-411d-8699-22c4a8d354b5',
                'd264d6cc-79b8-40f1-bdd9-2125633b8366',
                '5ed07e37-5ae8-4cb0-b193-7b0f6a1adfd9',
                '26ae7ab2-6b03-4753-9484-389e0f270c70',
                '23dbb227-490c-4710-b0d6-18979d1fd674',
                'c62e1be7-1ff0-4d91-a978-bfac19a4c713',
                'e1288854-c54d-4b76-8dc7-4177589e1daf',
                'f0ace11e-3b5c-4d21-b0bd-133af734d074',
                'ab6a4a15-9c84-4dbb-ade3-a128eb9047a3',
                '4d843d74-08da-4110-801f-4732bf99fcba',
                'e8286e94-9da3-4db7-b60a-40a7c8c3be6c',
                '976391d5-3a07-42c1-9f67-d96f8edd3152',
                '4dbab20f-bcc3-4dcd-8824-46879f40a2e3',
                'd47342e9-77b4-4b32-a79b-9eeca97a16d3',
                '54a75397-2c0e-44ce-92b8-b2ea74105f7e',
                'c5399dfc-8a27-4d44-8be0-571d4e9b8ef2',
                '5cb98eed-4899-4336-840b-ee6c80b9bdc3',
                '992e6d5b-95dd-4455-a846-b5ec2826b90f',
                '3d827a7c-1e4d-4c83-9940-b12030061908',
                'f852260d-5b0a-47c0-a3f4-427e92362808',
                '03269073-2635-4d15-9ac1-9aae4731fec4',
                '913d6cbd-9cb9-4c2c-a5cd-961b19ea8e53',
                'f8e3be30-f81f-4711-9b82-3b2c0c11422f',
                '1a551721-178c-47f5-9e1f-ce848cf389cd',
                'c8337b56-a616-4f31-b674-21a457837822',
                '4e2ce75b-0f4c-4634-8ff4-bb8a3d5fa796',
                'e9d665fc-f1fb-4f1f-a1f1-509e172e14bd',
                '66e69fb6-fc87-4ab7-8081-625e1a7118b9',
                '670e7766-79d2-40c9-9857-62554a3dad27',
                '495c2709-e8b8-468f-b555-169187a0e7d6',
                'ff502cbd-c84b-4fd6-84f1-03aa4753968c',
                'ee1039e8-a26e-46d4-990e-66a5f82967a0',
                '7ca0b681-7f09-45df-906d-e73ea46f5e80',
                '027c4ebf-6a38-4a8c-9948-043fcf315e5d',
                '3b66f885-fca5-4914-b564-46d85615660c',
                'e3b7e4e7-af7a-4a48-98c6-143de81a29e0',
                '300c6bb6-6ef6-4aef-9ede-13ad7ebbb0bc',
                '9469dd03-01ba-49d6-9873-21c516794a34',
                '6ffde9ad-936b-4ace-9dae-a7083f983cb5',
                'd3d1e114-5f44-4f38-ae5d-93ebe68ac27b',
                '9b26f80b-bfa1-4a94-93e8-dd8b1f7998a9',
                '1025fa68-7e0d-4aa2-ac74-3f944b0eca3f',
                '6c3891cd-1827-456d-a17c-5b7ab29a6113',
                '363d6cef-0dc2-495e-b722-574aa09a32d1',
                'd3e0bccb-28ef-4034-9ffe-0ce76263786e',
                'ed026d56-c667-47fe-98ee-66bfcb4ae42f',
                'd3033c8b-2790-4561-859b-454ff1612d5b',
                '9fb2ce54-6f07-4938-af4a-0b9b7e8c9a6e',
                '90799140-9041-4f30-826d-c18c973f8782',
                '80c400bf-a98d-4b34-9d88-c5ae8d01448f',
                'c7e4ca50-0654-4c27-84e6-0348fd24d02e',
                '1c337230-bddc-4b0c-b8c2-87861a348708',
                '63d181dd-8cf3-485b-93d1-7c5661c89236',
                'f0d86acd-15aa-423e-bb14-b561467c4262',
                'e679572d-929b-4883-ad02-2a43ef70d727',
                'b6a6b73e-bef1-40eb-afb8-80ea6cbbecae',
                '1f86536a-6831-4c81-adc9-345304935854',
                '177efd13-cf54-486d-8c12-6de79ecde4d8',
                '3b5f4121-805d-45ea-84f6-b2e3678e0272',
                '06a19073-e378-4247-93d5-0862495e3f5b',
                'fc6051fe-9714-467f-b47d-874d65ca9397',
                '327fb2eb-f63e-4d58-9ad5-4b61cc26c542',
                'c0cd0cae-6874-4446-9f66-a8237f093818',
                '1f3898c4-06b0-46ad-848d-31210e011ad9',
                '7c006379-da3a-4220-8841-e7fcfcc01d25',
                'e749873e-17e0-4aa0-a8c9-72d1db4539e1',
                'a4e608f6-8b18-4baf-a5a0-9625bc05a977',
                '87c84120-edba-433b-815b-19c95a98647d',
                '469769b5-1cbc-4aa6-bfc4-acf5c863252a',
                '2b7851b9-ba79-4ff7-8274-bca144cf9295',
                '407590a3-eb08-471c-ad12-4234997481e2',
                '3f52fdd5-70c4-4dd1-8f66-f80f4b112a8b',
                'b66f7595-7772-4272-9693-042e1b13240c',
                '37b7156c-c373-40dc-b3b1-ebda3c48a8fd',
                '0fd84e5a-d2da-4512-8e22-e2a250d1869b',
                '6d58f3b4-e470-4770-9d15-16a80e9cb98c',
                'efe6457a-4bc9-40eb-b542-72dabc72bc8d',
                '36cbf7af-854b-4aea-a7ce-c94a82b8c1a0',
                '692c62d5-9667-4b70-84f4-ea5778700f93',
                'a3d61c74-fdf6-4bf8-a8d8-b197621be70e',
                '0a97ca72-4d21-4538-afd2-b1e8f72c627d',
                'f6c5dfbf-0cbb-4264-8e6a-02dece345542',
                '24774cee-21fa-4b41-89f5-fc8a33dd5b33',
                '77cf85fd-6da1-4c46-b4ed-90887a5c2318',
                'de3f37f0-e05f-4d86-8633-0cc81de5bbc0',
                'af8a5ed6-e40a-4833-a2f2-920b9c83a088',
                'e93161f8-863a-467b-ac6b-5c77f1f2cae2',
                'a1b951a5-fd51-42b4-9b78-c5e917f69904',
                'e6e129b9-17ed-4b68-afc5-3b1fa35d1798',
                '4bd5687e-a865-454e-b132-c55ac8c79ed1',
                'beca6146-714f-4aa1-bcbe-26e1b5379f33',
                '3d055a04-b19c-4707-ae79-dc48f5a88c87',
                '76465b9a-87b9-45e1-8cd4-be598d21714b',
                '24e1c9db-3803-4940-8b81-341144e49471',
                '27ed6cfd-dc46-40fb-b667-eaa64aa7f58c',
                '66077745-4644-4fea-858e-d59131c1a194',
                'dd722e85-5fc4-43f3-ba59-23ac6a7416a5',
                '9febf114-9b0d-4289-971f-c706cb111a94',
                '433f7b96-1b51-43f4-bb28-a79d3696ba66',
                '7b25e7f4-25c8-4b2d-9ef8-2ccdc32a2765',
                '12e68d1a-823c-44e4-8a09-7a7f2f64cee3',
                '86758fff-664e-411f-ad11-5b4241e64ed4',
                '93570c11-6ac3-4552-8dd5-dfbccf019771',
                '94ace947-8650-4067-b503-a1a3bfc31b81',
                'cdb19d73-6a5c-4260-839d-57d5f8eb28f8',
                '2e6184aa-06ce-457f-8d1e-86a9fb453a2b',
                'df203f1a-5953-4808-933f-1adbe14f4c43',
                'f5ca6994-90d9-47bf-8be9-f0209c831801',
                'bb9b37e3-1583-43e2-8509-5cd3a638a86d',
                'f960853f-a138-470a-8d8c-74fffbdc45ee',
                '30f4f014-0bae-437d-9c43-55708f6ac950',
                '62ff29d4-a911-48ad-bf8c-53301a59dc62',
                'b23b6e5c-e475-4446-80a9-744c6a942fb4',
                'a3fab0aa-dfd4-43f9-a895-db185f9ac711',
                '1c6a7d3b-206a-4cf2-83a6-9af9e69f4500',
                '495fa7f5-bc7a-4527-b3cb-ecabcac2d2fb',
                'a45d7480-cca9-4e41-9b0e-02b70a9141b3',
                'd3ae9e8a-4146-44c9-9fe2-fce9d436a92c',
                'ba55d11d-a529-4c63-bfeb-c7b770a01e9b',
                '6a81a7c3-7284-45e4-b8d2-273ebf473d3c',
                'd7cfce14-b4c0-40ac-ac9c-bbe5254a8615',
                'b5c45cc6-2427-4639-a36e-91b990937b86',
                'c1cd7231-a34b-4454-a43e-b2f18d29b964',
                '66025939-f1c4-4d94-97d5-80ea490f9c85',
                'f172757a-1c7f-4b19-8c59-bcd3068b18bc',
                '6157097e-10b6-42d3-ba12-fbb45c474113',
                '45307661-ea69-417a-bce2-e44f3cef6a4b',
                '64664c2c-0af0-47b9-89a5-b6479ca398be',
                '06ed6277-48df-4082-8d04-aa8ac6872d0d',
                '117d22cb-800b-4b5b-a431-2fc74cf58e40',
                '5037b54b-8f79-4ef8-b5c8-e246de1d6c4e',
                '411fda3a-3905-499c-ab58-7b06a467b96f',
                'c9eee46d-898c-4b39-8d39-98d67fe72831',
                '3b186ae4-4ccd-480b-9cf9-982beae274d8',
                'dba7a085-82d6-4590-b206-aee96657bb47',
                'a6b8c337-4213-4f2a-92d4-84b2ddc19165',
                '9b0c76d4-bd5e-457e-b66f-168aa33b82a7',
                'dc4c07b1-5ba9-4384-86e9-71540adf0298',
                'b9fbba99-6aec-4734-8030-646ec4352e90',
                'fa0c949b-6bc1-4074-b132-d3987a6a8abd',
                '78019106-31b5-4786-a588-4d6bc03cab6b',
                '851c4c07-c793-4f67-a62f-0f7d35da020c',
                '53a3d52e-2a3f-42ee-a506-f4e456699c0d',
                '03e22663-0a14-4f1d-926b-81ccb0201fad',
                'c9a2f547-60ec-4709-bd05-b0081d04a054',
                '21643048-401d-49a5-8a27-198f8a68a89f',
                'e1e03789-35d6-4efd-a7b7-7d0054432c57',
                '23fb8ce7-b11f-4f53-bf60-12d378a5b9d1',
                '1a22cae0-22d9-4df8-8b1a-1927ca6eee2b',
                '25c436cf-7931-4927-afe3-12831a192aa5',
                'e629521c-6067-4241-9476-943edef6a048',
                '57c1769b-66e0-4175-9ac6-5f2a9c79926a',
                '7e2a6c07-c2b6-48ab-bc16-70edbaeb9780',
                '493a7331-510f-49e6-b3fc-b218216843a1',
                '1ec3d6b8-ddad-48e5-ac6b-d74de3c7e616',
                '3d4c65d9-6b1b-4d86-b4b7-4a127c0aa9f5',
                '0e781a58-2653-4838-b193-c278814d0b54',
                '940a7122-6f71-437e-99ae-e41d8907694a',
                '5e5f9ec7-89b3-4935-851b-8bcd13c961a8',
                'a2d37f56-cc54-48d9-97e0-d74a7f8c1534',
                '2b0d4b94-fef6-4a95-b9cb-68b2352d873f',
                'f26e1529-0a8b-4d99-b778-418f00e6a519',
                'aa80c5be-ab7f-4cf4-8db9-83a93b04b0aa',
                'efde8f25-3d5a-4e30-a3d2-f55757371bff',
                '41dbe7a2-86f3-413c-9f60-ad6875a30be8',
                '5ca6392b-4ff6-44cc-9582-9375ad1bcd80',
                '8cf87423-8d4a-461d-879b-5249d215c0df',
                '8ed38ae5-04ec-4eb4-8a61-a8f368012ba4',
                '35275959-858a-4f4f-a709-3fb6a244b0d5',
                'f12bd962-f62a-46b8-815a-c402e8caaded',
                'aaa2889c-a265-4ed4-bac7-794d2c4ef543',
                'aac892d0-3831-4e4f-80b6-06089679dfc9',
                'e412cce4-6e8b-4e8a-9d29-ef89bd269f0e',
                '79a8e56a-389a-4de1-8118-cb230f138d4c',
                '49a99702-5d71-4895-a9d7-eabf77b07052',
                'e422f624-243a-4179-82c5-a243c779c2d7',
                'e10d591e-f38c-46f1-9f82-bd0533c69e77',
                '088ada70-d2b3-4ef7-95ca-25fa67125ac5',
                '337bf4ab-92a9-4453-a997-9f8ef35067ce',
                '24938213-6edb-48e3-b34c-9825b82bbf84',
                'e3ad30ab-83b9-41a2-bb0e-07c59e922f1b',
                'cc7bbf02-5066-4591-9521-a1211404622e',
                '2d772d45-a911-4b64-826f-e603ae679791',
                'e024365e-f4ff-477f-99fc-299c84c2c2d6',
                '862a8035-d00d-4465-a744-d03a9aa73478',
                '5bc36070-6c2d-4eff-8871-990b48330172',
                'f016fee1-aca4-436f-bfbf-34f4710606e5',
                '72b1086f-7865-4ced-b5e5-eb5ccb9f4452',
                '7163a8c4-56f3-4207-8f03-6de1a616392a',
                'a7d2fa7b-975e-4ddb-988d-0426e5cf0805',
                '2175bf74-9494-46a8-9154-c496cdf631fe',
                '4307867f-0d2f-4ddd-ad24-428f6f8ccc76',
                '71e8f1ca-61a0-4180-83fd-0b5f99f52e58',
                'fd6f4cef-7dab-4950-972e-35b1bafe6a82',
                'f47aa558-c8e3-40d5-b5ae-608b766d66da',
                'fd244dbc-213f-41ef-8bf3-e223845607f1',
                'fa99c648-81ac-49dc-a81c-1cff419fda1b',
                'fc47870e-0384-4297-893a-1820cd6edf43',
                'fdc72eb8-4b5a-4d33-9a37-ae8ae8c778e5',
                '1ae5dc4a-f706-430b-a182-b29f8c6d16bc',
                'd9fab7f7-6f56-4a74-83e1-c9a8210157c2',
                '2199880a-7534-46c3-95a3-83b7e3b03f89',
                '54e8a3b5-1c48-4e68-92af-a9e6b863bcd7',
                'c2538718-f162-43da-82d1-50ba53e0f315',
                'c190fec3-1b13-44c0-8beb-8b3caccdc10e',
                '8af15a62-7c31-4068-a5a8-3cc9dc3d72ca',
                'ed8b82f5-61bb-413e-98f1-2b552166be0f',
                '6372e6b6-0d28-4d54-84eb-f97f2912b366',
                '69bcc9a8-93ab-493a-9265-ee9be98d7f08',
                '58a8fe81-fb78-4490-aa00-a5c253ed0aca',
                'e8ae46aa-59de-4903-a441-21b0fbcb21f2',
                '8fd11384-4f77-43c5-b450-0487311a0ed6',
                '076f8d0f-92c8-4c9c-b940-d6348fba7048',
                '3c0bc898-6ea0-48bb-a318-f7b514421b52',
                '5609c3a3-4cd4-4534-be0c-7f39546582dd',
                '6b04a8db-81a4-4dd6-8c6b-f26c4ec6b563',
                '00e32491-b895-446c-af1d-3a106c37167b',
                '95d300be-4a1c-467e-b7c8-9f2c54861e3c',
                '82f136a7-81b0-4d03-93c8-df067575946e',
                'f93a1adc-1227-4334-8d3b-d75e00977b6a',
                '39d6707a-b09a-4904-bd1d-44e489fc64f7',
                'c0ddeae7-9eff-40a3-a9de-bddd337b5016',
                '73418561-1e3a-472d-8119-efc04591f933',
                'f165d25e-2be9-4735-894f-75653a2d1ecc',
                '4213f0b8-1769-4584-8400-e6449eb15fd2',
                'f17d0b43-7a35-469d-ac30-61bed431d0bd',
                'be59700e-10f5-46eb-a8e5-d28f38bb481f',
                'd207bc5e-3110-48df-a2ff-afade86d6412',
                '50749237-c638-45f5-bbc4-1d0375fa9468',
                '4767fcb4-f620-42c0-808c-7d6e1328e01d',
                'c3d0151d-a310-4676-b8f5-403078332d27',
                '49beced3-4689-4d13-b89b-59a2331241bc',
                'bf658d50-d2c2-4ef0-8a90-95431c5e5e79',
                '9d567081-a0b5-4912-a9a0-05d7633b237c',
                'f453a4ec-e224-4690-ab7d-8aeb4094dfce',
                '7dcc6262-398a-4fb9-8dce-207712b975fc',
                '8039e05f-4ff7-4f0d-88b0-69095c494d0f',
                '9e4c5376-3802-454a-aab0-5db233e6cb9f',
                'eb8414c9-6dad-471a-8ca5-6e4a4a32ac60',
                '28f8a22e-b063-4bcf-8b0a-fd2311d3467d',
                '4ead47f7-44d8-4dee-9008-b60f56d3efcb',
                'debb3f33-c8b8-4386-aee7-40e1bd4dcd54',
                'cfff2409-e8b6-4441-b091-f9f272291192',
                'bc577155-9a47-4269-8343-96eeb91bc5ae',
                '5f7a92ac-f528-4056-bb2b-8530aca6080b',
                '2eac608b-ba5b-4db6-a42d-da45416f07ee',
                'd85a0767-d7ee-46a8-9736-4b61a3b55269',
                'd38e16cb-d70a-4b15-905b-494e0f8088fb',
                '73442beb-953c-498c-9acc-46c357038fc5',
                'f77e6518-683f-4cd6-8b6d-90d214f8ef3d',
                'ff2b406b-3d05-4679-9c71-9908b1ae1fb0',
                '3d519a27-c7be-4075-850c-1fbd89240338',
                'bc1e3f84-1e41-446a-bae6-88d14c602824',
                '256673a1-5742-412b-b4db-6ab833d1b571',
                'daec3a11-a213-471d-add4-72e08701f70a',
                '10f121b9-0313-44b6-90c1-7bd84bbcd258',
                '57286775-1831-4c90-8ad4-9bd3a0043b8d',
                '029c1929-0000-4f69-97cf-7277c0d88341',
                '0e0ea959-0da3-4957-8e31-d1195c80bbd7',
                'b7a76b3c-ff5e-47dd-876c-a4393eb3daa0',
                'c15af1fa-0bc3-49a5-b41a-538c65af86e0',
                '5b882de6-63e8-4695-baa9-24095cb90c77',
                '3e3050cd-378c-4bb6-b07a-c474f3a3237c',
                '24d3c39c-6e42-48cd-9b41-a8e45d4ba2f7',
                '8b64e92d-5f4d-4226-89cd-617c41034f07',
                'e0155923-ecd2-4ca1-b270-c5e252370eba',
                '71f9e0e0-6fbd-4ba0-87e0-33bc52aaad24',
                'f7f0cefe-8f9d-4086-8c36-4ed5aa4bc07c',
                '70ef0257-f8ae-4500-98e8-d43044db7c75',
                'b4709fb2-d03c-457e-9fdc-9f12c2de68ce',
                '8ed66b93-b946-4ba6-980b-5d92646ed994',
                'ad875657-faef-4d4f-a632-dd6463d02741',
                '91d63855-18b7-46ac-b66d-51ab4ed53f1a',
                'ff45acc7-4ab3-4108-847e-defd5a890295',
                '62c547c9-c5cb-4a89-9763-57c6f314702c',
                '25702d44-3eb5-4f35-a422-2c932eb59b4e',
                'fd49bb8d-bb8d-463c-b18a-419b070e266e',
                '8f82c35a-bf4e-4bd1-b057-1a5a9f266450',
                'e1aa8306-52a4-4c97-b3ac-c9dc0fc018c0',
                '4ca3d725-6e1a-4cbd-b5c9-9dcb96c63cf9',
                '18c633ef-a511-4f20-ad1c-87b25f0fa943',
                '68af3d87-c2df-47ad-b91d-9c7deda8badf',
                'c2d47e6f-9913-4f25-bd4e-d0ddcca323a6',
                'fe8180e5-083a-4023-960b-6be311dcfb1e',
                'e76275e2-c5c6-481c-83d1-8caa48ed41b1',
                'bbafe5d8-c738-438d-9ca6-a0d652015de9',
                '3259645f-7d53-4f9d-84bd-6aefff6a617b',
                '05a5ea3a-4cfc-46f8-a7bf-5e55890c5cee',
                '74f7eb7a-2611-421a-9ea8-79d9ef9aef60',
                '54dc62a9-9c82-404d-813e-47d916ad7aaf',
                'd5523ccc-7ed0-4d1c-893b-21e68184eb3d',
                'b61e1480-3946-4881-8cd3-8602b6d11d57',
                '85851701-c3c1-4e20-92f8-c8e61277f645',
                '4dae3d49-eef3-4f80-88f6-7cf95e0c6720',
                'c435e493-d965-4650-abdd-2623a86b3abb',
                '9ef49371-d7f7-46a8-bfa6-bbe82df6d4b8',
                '9c36f306-e84d-4374-8faf-d740720df815',
                '321fcbe4-ff82-4b69-a83a-6b257fd3c789',
                '439fa238-b842-4425-9731-6f81c113b456',
                'd508c159-4967-4dbc-9e87-620ec3f78bdf',
                '79efd17a-9007-4d75-bdac-198c2ded13b8',
                'c6a1f888-9edc-47b9-a8c2-ee89f29e1072',
                'de6e0420-3663-43d4-b963-1e33e87b1dcd',
                'bfc61569-50e2-4e1d-bffd-135884cdeb68',
                '0ca19499-b720-4a02-8f3e-fafd0693a2d4',
                '1f6bb18c-9115-4731-baee-275d09ebd4d6',
                'bf8dbec6-562f-48b7-85dc-c4a7be488cec',
                'b7cef2e6-e995-4a0c-baac-b24ebb22d1f1',
                'fddc53ef-275e-4737-8673-8b57fdcc93cd',
                '3f1b45c6-a5b7-4e12-ba4f-9e1e3454eaf9',
                'cf901036-5104-4644-85cf-da50e5936ef6',
                '871f0740-527c-4c9f-802b-34d4b7003d35',
                'fcd670ab-7ece-43f8-b788-12e11056072d',
                '4e6f60f9-5025-4380-bebb-7ce009af41f0',
                '6cb69f84-1561-4d97-ac0f-baf2d7668aac',
                '4c506948-e6da-4d39-886c-bc4bc604fb31',
                'cc749dc0-3533-4c7a-8ff5-73f84c848742',
                '41d6ad4c-90ab-4b74-9443-8b32589e728e',
                'aa1b9441-8f38-4f42-8fb0-4a647299aa78',
                'b15ec7bd-7b3d-40bd-a44e-1dfd9af51dbb',
                '86a66971-0e4f-4b84-8a73-1bc4bfa089f5',
                '73e9d06b-e09c-417d-8567-5c2274834069',
                '1c849f64-7d36-4f06-9e42-3a5e3b4e8003',
                '45a8dce6-8d0f-4903-8c8d-b41c52be83fa',
                'ba2ce08d-09f0-41de-84e2-ea562643f8a6',
                '0de43ae8-d83c-4518-927d-d81282c3817d',
                'dd80ec15-5b3b-4d8b-a1e6-21d24d822ba2',
                'eb65e523-ffb4-466e-a1e4-57ad77286c36',
                '20b329c8-7332-4397-8b24-761e52471705',
                'b1f4e15d-533a-44df-aeea-df6ce4647c0f',
                'c5cf7f61-6663-420a-a539-1d0dd720fab2',
                '7e28a5b5-3e79-4d1c-98d0-15d31bfcd47b',
                'bc0bb28d-2a23-4118-a17f-a20ce4c2cfff',
                '0fa1a59d-2ef5-49e6-8a0b-ce87c338fb31',
                'af652e62-fea5-42dc-a98a-9b0f17cc5b8b',
                'dea00427-5907-4539-8113-9aefcc9a2007',
                '0ccb9aba-d7da-4b0d-b9c6-290c3fca41a1',
                '228a28db-ec08-4e29-a4d3-338aa00ecae9',
                '11d036fb-a56d-41d8-8d8a-ed195295df56',
                '9173d48d-1d8c-4e7f-87d5-3c744c33aade',
                '01ec27eb-c5e5-482b-8ab6-c94a5b7f3e61',
                'd5216489-e0b3-4627-adff-152674662a8d',
                '9bbfc27a-de4f-4ffd-a4ac-4cec826a25b9',
                'fc0f08cb-1def-4612-8b1a-0bf805643ab8',
                'aaa92be1-0742-471d-96a1-785a3b81645e',
                '551622c2-e17c-47b4-8131-bc429b4beb1e',
                '53db1e55-7f2b-4c2b-bb6e-71388bbd54e3',
                '230693d5-c8f5-45c5-b89f-40bf62b31f0f',
                'dfc63204-988a-4af4-a1aa-adb16e693a08',
                '36e05f4b-7640-4c92-b4cc-7e7814e671fa',
                '0180a088-8663-4838-a8b8-8d5115e73afd',
                'cec3d6f9-1e96-472b-a3a9-c1a6cc0ff4fe',
                'af634a3a-ce4c-4a7d-9b8e-3749f3b5cf62',
                'bf62d292-f380-4ed4-bdc0-8b76fb335718',
                'bb4a46a4-601e-4904-a9a2-f04ac7a3b2d5',
                '003b3fc0-a8a8-4606-9568-ea72c15fd5f3',
                '209da695-21e7-43f6-bc2b-039e19ef7d99',
                'e16999d1-dac3-4678-b599-76aee89214fe',
                '80b78ac8-4cae-425a-b55d-d0de13dd51a0',
                'c2aeac0c-9e32-4b0f-ba43-567eb67482d1',
                'ee5f4fee-eadc-43c4-b18a-2d9350c08c9c',
                '18f2c791-eda3-4372-bdcf-09c2e49c6bfc',
                'ea5f6496-c9d6-42b4-abdb-229d9abbce98',
                'fa273b14-0e9b-40a0-8a87-1a4efe4533d7',
                '593af6b2-fda0-4d8c-9469-40bd64fe349b',
                '300f3bd9-3ca0-4375-b016-bad651e9964a',
                'f5620679-30ef-48ab-ab3a-e5c0fcc0466f',
                'ff7d8725-c668-4567-a2b7-61f1a09c204f',
                '3dc7da2b-b7ae-462d-a6b0-a8cea6e2f387',
                '6eda82be-3354-4f65-a9a9-0eeabbf153eb',
                'db8b0192-7263-4e33-a54b-6f7b27002e53',
                '42215ed4-819e-40d8-834d-25e1d4e765d0',
                '1d30b6d1-2654-467b-83e7-9efbd5c77dda',
                'b6efedb4-d212-49f5-81d1-5d85ffb79d99',
                'e0c4576f-adb9-4f7f-b940-34d07b77f8a7',
                'a63f17bd-8ca6-4985-8f61-88dc1f196f03',
                'a3dcb8e0-f5b1-4d17-b721-a58e27902dbf',
                '1edb39a5-8ba4-415f-b67f-166f5eb27e8e',
                'a0019dce-bdbc-4474-99e8-7e832d3e2dee',
                'b9dadabc-1135-4137-b60e-39078cff7b4d',
                '94b8b8e1-db60-46e4-9771-6989ec494946',
                '6bbf7d01-0c83-46a3-a1c7-b6cbf5d542a1',
                '06b73d4f-f678-401a-b4ec-b46bc0735a39',
                'bbdf15e0-1069-46ff-abcd-77655a107f22',
                '24ef5793-42cf-4f2f-a6ae-fc00cee36498',
                '8d025c38-9d4c-42fd-b82b-7e7ca0eb9c7a',
                'fd1259c6-337b-4e46-98f8-a7c8ebb631ef',
                'b2004d5e-b91e-4c4e-8286-e9bb53ee68d4',
                '47254335-d25f-4363-aa6d-6cca4c79d762',
                '8f361b04-93cd-4f93-b935-99b5071c3b25',
                'be24cac8-81d2-4752-930e-9cfa5c00be79',
                '0a936f85-f16c-4cf0-bc35-b706b74b612e',
                '0786dd52-1757-4094-945b-0768efe4e756',
                '7917f2ba-1fd7-432b-b196-14f606059bb8',
                'd92b8504-cb35-439f-a0a2-41d8f395d50f',
                'b207475c-3f00-456e-bc87-7826c7c0cd85',
                'dc956b54-3c26-4909-b301-aaa84e18943b',
                'adb81dc2-06fd-47b5-b9f3-eb52b4275e28',
                'c3c876fd-4b81-4de3-b38c-84b1b093a290',
                '6683ee81-3ba6-4308-a14f-7f7052089535',
                '15536bee-5ee0-4e0d-9b47-95b0f16df258',
                '245d4c52-f120-4596-b5c1-a63302302c5b',
                'b51a25ab-bdbd-4217-a293-ac786a3a38f4',
                '19ed93ef-ceb7-41bd-98ab-2be3dd8911fe',
                '51aedd74-7d3c-4db5-8bf5-b221ae874d09',
                '2c41fdcb-fe5d-4453-bb69-9c269f0c17ae',
                '5c67df3a-cf68-4c38-b6e8-57b02905929c',
                'e520a163-fc6f-40cd-bc1e-6dc899732522',
                'f2ff2246-5049-4775-a35d-b188ac8d93a8',
                '45aaa81f-0e23-44b5-bca9-889bee6d8a1b',
                '8512249b-a63e-4266-8e57-cd1fa98eea25',
                '31d0528f-7b56-4747-a6a8-e3906bd6c3bb',
                '1eb212c5-e104-41dc-8240-84bd7a6e25c3',
                'a6891928-e756-4302-9bf0-2664b0eb85d8',
                'a4c04982-1f0b-4c9c-ab60-04894cab6657',
                'daa4dafd-5a69-4593-8ee6-5712ff1d686f',
                '92ba57fd-6150-41fb-8469-9cd2ba63a717',
                '6445112a-b099-440b-847a-1ab73b06db00',
                'c68dbe3d-9fa9-414f-ac7d-a5afeabe7fca',
                '59460d22-3aa2-4f17-966a-d8e852ada932',
                'd2f15605-b54e-492c-b2ac-bd7c94468d85',
                '99b09a9d-eb84-4412-b105-11c016b478e0',
                'b609168c-71b2-4183-b014-bf75d9eca72b',
                'ee28a7b0-cc1a-4934-aa25-4e9a5bb69023',
                '138b4f26-b75e-492f-9d01-52764731da9a',
                '20f6f502-f061-4eb2-ab15-5742bcfe6a2b',
                'b274197b-c23f-4d32-89d8-6ab3038c44bc',
                'ca140f8e-d807-42ae-b314-f1e9f0b573c3',
                '6e214811-c273-4d3b-88a9-f82eb80d4ce4',
                '6071be28-a393-43ee-8071-c9243d7166b9',
                'ee7516d4-4fa5-4e29-904b-b633085faace',
                'e8665083-472c-4629-9981-80e3d8a6620d',
                '0b4c71b2-1258-407b-b3de-9389be9a1bed',
                '8d9498fd-d40f-4644-a498-0f26d60fabac',
                '767a3e2d-3e57-4acd-aa76-270308808961',
                '8780ed86-28af-4ad4-a325-5d48b17ec50e',
                'e8628819-620a-42b0-b037-afac964f6a9c',
                '62b7eb36-b6e9-45d9-b55a-cba00a783f3e',
                'de7d6f33-6458-49ee-926d-1bd218d193e6',
                '05d143c2-9293-485f-8685-94e9eac21229',
                'be98f701-a473-4baa-9d34-c0db15af1a7b',
                '46aa8f5b-998a-4fec-9c22-ee84838f9b7c',
                '1bd6af21-8c7c-4dc8-b7ca-b03bcd7ec422',
                '225689ac-90d2-4fbf-a65d-88b078d5908e',
                '2c0c91ed-4bae-4651-9e30-e69a77b95bee',
                'f782d1bd-469e-4bb5-b678-668be2e532cd',
                '17af11cd-e899-44eb-88ca-459c002fe77c',
                '59550c95-032b-48b3-9c4c-264e15954df4',
                '029d9cd1-9c79-4818-9b4f-d52f6aba1bf3',
                'e6791583-330b-48f9-aa3f-8117e1adb36b',
                '4e2bdef4-1a69-4ade-a68b-ad74705a7e45',
                '93c98b26-5b30-405d-887b-bf3df2cb5e89',
                '9fffbc3d-5fc6-4f08-9b69-5e3b94917ecc',
                '7c1e1afd-4f48-412b-9c58-b6f5002e92a0',
                '2ae1fead-8d4e-44ae-a6e0-f8ab7539b70b',
                '9e08f3ff-1c1b-40cb-aab3-28a3a0ec407f',
                'df57a42e-465f-4cb3-ba85-5f13a3bcd323',
                '2ca76654-71a5-4945-b83a-650b6fad1c37',
                '89fb5c5d-b0af-4ed1-bc51-5ec3086e27f7',
                '6e887701-3803-43c0-b998-4a89b9c608a7',
                '16128bcb-5524-4c9e-87ec-a1eaf9ffcacd',
                'bad15468-3048-4026-8080-fb260257f3bb',
                'daddb49a-a137-4cd5-842f-cf20add4b2f5',
                '28b7d135-860c-438c-82c0-a0ed37539586',
                'fe6e5848-50ba-4b68-88ae-f0adb745b878',
                '7b7a5daf-6b77-4716-9a14-a75a3dd30133',
                '396eadfa-6537-4301-bb05-8595b143eff6',
                '8cf28d43-8839-4824-b798-6d054877b359',
                '604aad0f-9f32-4a7f-8f46-cb0a1b3686c5',
                'be632c5a-df52-4d58-a6a7-ce040f5956e4',
                '655aa6c9-edab-44f2-bf3b-4230c0227ab3',
                '5b9ebd00-7480-4f67-945e-6e237e980e5e',
                'eb242c19-3adf-44f2-af88-ea6aa4002bbd',
                'c5ef1756-4df3-4f0e-810a-034809a9401c',
                '82d48dd5-df4c-4aaf-941c-31a7a7a6f7ff',
                'ff5d3dc8-8bb9-4b14-aaec-211c059e9976',
                '64542798-0b68-4f1a-8598-aad373516e47',
                'ec06b4be-d32c-41bf-afca-d33de9f3a9dc',
                'ff60b14c-a03e-4bb9-a30f-2acc0e076783',
                '9cab3ff3-c0dc-4c1e-b69a-f0f98ed51de7',
                '0d2332d1-3cd0-4a1a-b820-8d584644c536',
                'd2ff82ea-879b-40c1-af5f-a4682f5d2ff4',
                '00edc93d-35ef-4fa6-ba03-6e6912d90d05',
                'bdc9d6ab-0221-4252-ac86-946c92ed1e5a',
                '2caff44b-732d-4a30-b055-1635ccc06cf5',
                'c5900524-aa4b-4be1-b050-9977ae1581f4',
                '69c6c72c-7002-42a6-92c4-e1b9bba8377c',
                '4fa4cd19-be6f-4d56-9719-6c77d947f43f',
                '0787d480-809c-4656-9fce-39161ce8baa2',
                '693dbfad-7073-4ac3-8e09-48727fc2d677',
                '4e93a2a8-d0a1-4b69-932e-e7eb33ed2403',
                '5e06e920-4b16-4872-9064-680dbf988fd1',
                '804c3745-497a-4b87-8813-4209c06e1bca',
                '30d8527c-d08d-4e12-87d6-4b0274273b70',
                '76a28602-4b78-4b10-a75f-16369292b7a1',
                'e5d23e1e-670a-421c-85ce-4f93dc83029b',
                'cbd58cea-c482-4c9c-8d97-30fcdb3f996f',
                '366bf663-1d4b-469b-813b-899072a85ce2',
                'e76e5f0e-71a3-43ce-a3e1-4f02755c9273',
                '66558812-9456-4257-b274-ea7c0682e5b6',
                'ab17909a-2b38-4ff3-9d15-d9e16ab5d5d4',
                '559aaa33-52f6-454f-9a2e-388187a92946',
                'afc90fc0-eaa2-4bda-b66c-e9d87dc9c1c6',
                'b11fce20-877b-42d3-8bde-211fbe6c7b36',
                '685d9e7f-7b51-43c3-95a6-e993ef5f9a46',
                '07621634-46af-4210-9d22-c7e73262df17',
                '7fd2681f-707e-4ba7-b2af-ef228bcf2b8c',
                '5b5d6c78-0e16-4a34-974d-241d84c1a676',
                'd8a446b5-9240-48a3-8036-b81cc7c8bec6',
                '1eb1dc84-6baa-45e9-9465-44069230b00b',
                'ede3e687-ecde-43ad-af6a-c9e458631773',
                'ae89d9f4-0e78-43dc-bdf8-056e2466ff2d',
                '4707ce45-7d74-4808-8b3b-52750a02bcae',
                'e0b0cad3-c4ad-4871-896a-fe6ae03f04e0',
                '878a425a-bb97-44ee-a953-6ba28382b29a',
                'f63d1e6e-c39c-49fc-ae36-e9a3f48596f5',
                '0d95fa2b-2046-4483-96f8-bdaf83c808b0',
                'c0a62c04-8267-4ae0-aae6-0972e656ada4',
                '3698715b-a968-4b76-bf30-e66771cc4c56',
                '7e469b9b-defb-4b03-966a-9836ff9092bf',
                '4dbfe3d7-7ad4-4ae9-bb08-7f8b0a5e7ae1',
                'f10c01eb-1645-4801-ab87-e6d290796f64',
                'c3e51798-d90a-4283-8c88-c4cf21e9b1a3',
                '393eda58-9c83-4fde-a232-6028393d76a5',
                '4ee287c7-e370-4618-ace7-5c6402440ed5',
                '02590453-dcd8-43cd-8dd3-269044eaf50f',
                'd2d59ba7-9364-42b2-8834-2bd7a9b80f24',
                'ff48c21c-67b5-4e50-bd11-f1945b0203fc',
                '1c0a4846-d39e-4a04-98a6-3d32d08f1b93',
                'da8611ee-228c-4a42-8d75-52afbe2cf1f4',
                'b3ec94cf-a7bd-4af1-8407-bf068e60762a',
                '864593cb-e04a-4938-86ab-f3d88b511c29',
                'ccdbcb97-615b-4f72-a41f-3090e7681983',
                'd8f58591-4be8-49ae-af49-afe2e5459d3a',
                '5e1a8bfa-cca3-455b-8ff0-078e65739587',
                '6fa66768-c772-490a-9d25-a46988c187f7',
                '8612d810-038f-4318-927f-9a38613f4a0c',
                '81a7d3c3-a171-42cb-8520-094b9ea8406e',
                '7558dffb-9fd9-4f4b-84ac-064e22fccc05',
                '1e9364e9-18e0-4ad3-8c30-35c49f75306b',
                'a893be1e-cb33-4285-8847-5ebf1ce141e2',
                '6d7c158f-fee7-44a7-bfa2-bdb734c624a5',
                '34a391e1-2a6d-45e6-ab06-fed411c6149e',
                'a385118e-18b7-4cf4-b712-102dc535e030',
                '332d2251-537c-43f8-9e9e-cc37107e7359',
                '95e90971-3c3e-4090-8948-08273dd5c13a',
                'a7061544-29cc-45c6-8f15-fe90f53f7eb5',
                '89c62134-9b76-4a62-ad31-359c73325739',
                'a6e91546-0bed-49b0-9cc3-5ccbdc4a27d1',
                '1df84627-24f6-4a74-beba-1e682b8c3dba',
                'fa1cc6a1-97f4-4877-b858-7b6ff60a98df',
                '20135a58-dbce-4a96-b85c-381fd99c0b1b',
                '0c9826d1-7169-450f-97d6-8d3aff5633df',
                '861d5e4b-2ba7-4e54-af1d-8efced47b840',
                '4302cd3b-55d0-4fb8-9d5b-af7a441e08ed',
                '18f998dd-2907-4c0f-8cae-d68891512067',
                '1ed1fa76-fd8b-455b-b8a3-5930d7e6201a',
                '045ed13b-0c7f-4a48-b80e-f180ee609505',
                'e70e6ac7-7ea2-4ae2-9f3e-790152747dc2',
                'e682667b-2384-4cd0-9e73-1f0c434b45b9',
                '2552ad08-adc5-4503-a18e-f4c44e0b526d',
                'e4b741cc-ccfd-4b29-941e-cde29e75339f',
                '06349f6f-3ea5-42ea-a60f-627550d6c4ba',
                'abc87a6d-fa2e-424c-9ba9-f04a00909018',
                'eeea5e82-3126-42ab-ad72-32cd3e7b36c7',
                '9f7412df-05a8-444e-9805-eab76abc6b1d',
                '1a2b9712-0631-47ae-8708-ea9b4b96a8bc',
                'fd192b8b-1f11-4f76-a9d6-8b3ed1f9f5c8',
                'be318bbf-eb7e-4732-8d1d-8e3ddd6a7598',
                'b355b6af-c64a-4d51-9b94-7d9e27dec31e',
                '7c4008db-c2ff-4e48-9c9f-2b20c39db63c',
                '6c2c4183-8345-4078-b2d9-b7dbbfbd2d9c',
                '8dac949f-413a-49a3-9e85-58163d1984ed',
                '930ff882-dd03-410a-afab-7cd731e6c1ca',
                '53376dd1-3ccc-4af6-9f86-2499b0c3f1b7',
                '94521e5a-19a0-4bde-bd8a-1f9c44696589',
                'e4a12423-1185-48b3-8c7e-297264f90407',
                'dfefcf3b-85f2-4d8c-814f-23df2231deb5',
                '412402e9-9e1b-4bf8-8860-e6e46b1711c6',
                '8c891678-ec06-4550-944e-16219df75794',
                '2647d945-45ce-4546-9a97-2fd0a85bb0a9',
                '06f92c77-20a2-428d-8543-e79fb6efcec0',
                '551c90ff-cc57-44ed-abbb-6e951369652f',
                'fdcb22d1-a94a-4b26-b8b6-b15e8da1e5aa',
                '7511ff9f-f67d-4f17-bacb-00854a865786',
                'bae49a08-ea5b-41a3-a045-3f0241042e0b',
                '22455377-cbce-45d1-8ef1-a62cc908e73e',
                '86d1889d-d9c6-4e43-9545-10bbd89a806b',
                '638a1d79-5002-41b0-a769-ce212f39d6bd',
                'fab3c59c-5e7c-48f5-a459-37729e682d98',
                'd46b3a3e-629c-4d87-a4fc-023f676f6d65',
                '04e004ae-4720-4914-8c84-8ed74d979188',
                'befb9d08-dcb5-409b-9704-57738880bef8',
                '6945431e-20c8-4495-a172-b4c4961a89bf',
                '866528ca-ea5a-47e7-9f86-5fa2ca67e075',
                'efa633fd-30da-458c-8e4d-837a87394172',
                '963c519e-55d3-4bb7-8fd8-c4004c366386',
                'afa9562f-61fb-448c-9c21-07ba7fdf9d37',
                '51c2fe0f-87f3-498f-be27-e60a066c1cc9',
                '80b2aaaa-fb0c-436e-985a-b0b193f7dd9a',
                '2d3c8d8a-5813-4208-afa8-0aa9c8230b12',
                'f2e37b93-12ba-44d0-8346-6e7142148a58',
                'c1933630-2072-44ad-9827-6874fbd52ce4',
                '6d1bec8f-2680-4f6b-a4a8-111b89317622',
                '45381612-6fc0-4029-afbd-8cd38ac486d8',
                '846eddac-1a26-444e-b184-0b9d75efe527',
                '41a2a967-b320-41a3-b11f-be45fbefc8b1',
                '201c2bb9-2b4e-4e92-b10b-8ae0afb2893d',
                '7e388139-d751-4746-b459-92c66208bbfe',
                '15fd79a1-6eac-4cf7-a659-94f7ee06b594',
                'a4469e68-be74-4c1f-909a-b60c46d6e579',
                '0ff53832-5017-41a5-a1be-6a1217edc408',
                '29a62e70-0c67-4ab5-9753-fae1414cf715',
                'a52e89e3-ce20-432a-93e7-d3fb4a61f0b3',
                '317646e0-3ae7-48b0-9041-fb802f8d776b',
                '06755d71-8291-416c-9b80-9ebdda106e0a',
                '5764fdc9-b7a9-4dea-aeeb-92aba8366f45',
                'a343233c-599f-4dc6-9d6c-1ff816aa6bcd',
                'e1fe54e3-208c-4677-b01b-9aa8e9dcca80',
                '0ee4e5bc-fd70-481b-aa94-dc3d65c9bb92',
                'cd8b9925-ef54-40ef-833f-5d07d3a06c8d',
                '98125969-697c-40b6-b6f0-f66a1e55d045',
                'bcd86dfc-3d28-4367-a54f-a22ab6ad8050',
                '0633c183-0ad4-40a8-af54-4c4c1ac2c4c6',
                '7d52837e-48a8-40da-9e78-8da7211c328d',
                'b62ddf6f-b5bf-45d2-880d-7ec4e7616e3e',
                '1e14db92-7ead-4e8f-a41f-db33f2b571a4',
                '5bbca2bc-d6ec-47da-8780-31bd09a22210',
                '9f22ea74-6887-496a-b361-32e0b62f53fe',
                '610c2fcd-8594-4bb9-8bb5-fb4146778367',
                'c57b35f7-01c6-445d-adaf-a87495440ec9',
                '0e25c11d-3562-4257-802d-db6957add9b9',
                '82eeb49c-d811-4021-a668-8fe7fa7f6a1f',
                '55e1d3fc-9d2f-4e65-a1a7-12b32e142448',
                '3d2606a7-1e2f-432e-8651-1d3cc9dc6072',
                '417573b9-f6c6-4bf7-9a12-28f9e50e81e4',
                'de455068-d0cb-4902-be13-c9130add3e94',
                'd3009fd4-b073-454f-a5e6-39922c2e31f9',
                '59787ed2-9fb8-4526-9346-2eb4e7c1e4a4',
                '2b3bedc4-2255-4357-a08b-fbf6178cfa7b',
                '95d41801-8a50-41d8-bba7-37c155ca571f',
                'ab09ceee-858f-43f2-8d4d-e9506cf117e2',
                '6a107542-4c2d-42f9-96bb-3f874944f9c0',
                '6604454a-6229-4782-bc9b-586ee151fef3',
                '8dde7d52-d923-4133-ac5e-1fa6bdc1ece1',
                '569e9b61-4866-444e-a5dd-3d1c12ef57eb',
                '65108e4e-b5a3-4710-baf1-b3088e1b04ad',
                'f6683ba9-b949-4e04-a072-a71f3b8a8331',
                'edaa1055-5a90-42e8-a424-5a9fbb74e7eb',
                'b862e430-e654-446b-b8ce-78181a0fafe0',
                'e23157a3-c833-482e-94b6-99a184aace4d',
                '0810b6e3-1db7-4794-9080-8701899f35e8',
                '05a49e53-f4e9-4478-9fed-38c3b886ef73',
                'a91117e1-45b3-41df-820e-b50705c72cc9',
                'b0ed86a3-7ab7-4552-98cd-4de71cda8408',
                'cfee373b-5db1-487a-9923-b41c7fcb185b',
                'a3da512d-8eb8-4706-bef2-41167ccd9a8f',
                '9d6864fe-b9ab-4c8f-9910-6f42112a9f1f',
                '693e7442-a398-43f0-8987-66a2e0e79339',
                'd22f20ea-511b-4785-94e1-1931a8b43a2d',
                '4f976f28-037a-4b71-a797-70df4b38ae05',
                'ba88db3f-ae36-4120-908c-d19c4765ae3a',
                'b97cae06-8260-4b2c-ade1-22345f7c09e2',
                'c5639898-07e2-40cf-a806-d02a20fd56bf',
                '1664c72e-124c-4af1-bb61-528c1f9dcac9',
                '6f799c6d-adf2-4865-a0b2-adc7dab43a30',
                'dee9f5b8-94e8-459c-9399-e7a9b14289c1',
                'a406b855-73d9-4442-961a-1a33d54485eb',
                '4505610c-c8ea-4744-a946-96d730379361',
                'b88f0bb1-9ac1-45b5-acb8-74a939be7c34',
                'e1cc1b2d-a418-453a-a49b-e35b08e8b3fa',
                '5bfa1e54-e095-4bfb-858d-81b42f4a5db7',
                '1d0ce5c3-4f76-4d8b-904a-6de98684b785',
                '0317b7bd-c763-4028-8b09-1a205b811a64',
                '73722c6a-2692-4b81-aa3a-327e4eab6800',
                'bb70b6e2-add7-4cb6-b1d7-ac060b4145a2',
                '9c62d473-fe7f-48d4-9ad6-cefadd0bc1ef',
                '220adeb0-0618-4118-b8da-d437aa4f5247',
                'afc294e9-b20f-40ad-9d46-b060718fe796',
                '9df2eb2e-eb09-46f6-82f3-c3e07b72f175',
                '23b36714-18ec-4abd-b718-56805376e78d',
                '2ecd1ba5-a3f7-44ed-a84b-c92b4ee95afe',
                'b58beec5-0165-4a66-baf9-4ca0d2f900d2',
                'edf69196-53c3-42d7-89fe-ae9ee6800827',
                '8412aa2e-fddb-4fe6-a143-ed87d65c33a0',
                '110541ff-c03b-419f-89bb-8c7f1f7ef40c',
                '99185b22-63d0-4a4d-817c-4eb887c20e33',
                '043d124a-bada-4be2-adc5-990be64dfd15',
                '794f17e3-daf1-46f0-a807-907ccf3c6edb',
                '8d3f58c9-8318-4c09-977f-3d7ee3ea32dc',
                'a859a806-60ef-4e39-b9dd-cecb8edd1da6',
                'c968279d-4bee-45f7-8b6d-7822e1818568',
                '6d1556b9-0af2-45df-927e-50b9de5656ac',
                'b909beb8-a309-46ab-bcac-924ccdd7e20b',
                '966cebe5-b7c1-4494-a37c-f990095f3aef',
                'fcc52cba-e89b-4a55-9a76-60b3ca34c605',
                '7d66d6ce-3e7b-4ce1-aa55-ee56e741b1ac',
                '44a228b7-d538-4751-92a6-1cafcad5a24b',
                'da2956ff-2568-4b08-b80f-d98f86c9fcd4',
                'ca8ef056-54cc-4a8f-bb2c-7e7273f961a6',
                '1b61b91f-5bec-45e3-8f7d-debc37b81522',
                'd138ea78-eaf4-44d0-9703-486181fc2e03',
                '095bf3da-12cb-4baa-9f73-e6a0c83f1698',
                '57edefb5-b499-4585-9acd-298107f09b28',
                'bcdbc446-687b-4154-8243-62714a97675f',
                'dacd2b20-b973-4de3-8d1a-3ae3bc0a66d7',
                '52e88f66-28a7-479e-8f32-de28f15a5746',
                '4738d138-f460-494d-ac07-d091876e9568',
                'e370e806-2f2c-4c03-905c-23bf27b6e24e',
                '42360323-6c6d-4446-83e4-af495a7f67bb',
                '6920e1c8-2318-4a8b-808a-860434a1ba52',
                '9dfff830-c541-4c7e-b361-ea50ab2dd657',
                '2badf26a-1e47-4676-a613-1187e472a7e1',
                '0166c05c-e42d-4d74-8694-c5c66bb0e19e',
                'fb3734af-b72a-4eb8-84a8-c1444bfc3d20',
                'a1626935-9444-4cce-ab0d-4bab43c71247',
                '1eb4c262-f59f-4b36-a410-144e5e046852',
                '5aab979d-3e56-44a3-a45c-3651cd1b88f4',
                '21bedf66-8689-45e0-86c6-41a16e3c024c',
                'a6c8eada-9e9a-478c-85ac-a0d40175f524',
                'edc3acc8-557d-43c1-a6f7-afcc83ca1c3c',
                '3607cdb2-a7ca-42ad-b380-9b303bdd4afd',
                '793f2856-d1cc-4240-b64c-9109a1645c9e',
                '65cfbb52-9feb-4452-b09c-8c3afeb61a66',
                'd6e5e553-a677-40c6-934c-a9c17cc460ee',
                'e3413a57-7ca4-46dc-a962-8ec2ba86f7a5',
                'b3e91260-95b3-4e9b-acfd-c4e465762712',
                '4c5cdf6e-175f-457b-8b9a-2b5508c5e8e7',
                '6ea541f0-c9f5-494d-b5b1-a49ffdca33d0',
                '44b71e06-5810-48ae-a87c-d079e71cc2ef',
                'afb9db0a-0a83-44d1-a340-149a06ea6785',
                '5821bd20-fc52-4df0-a971-6c98242f85b2',
                'c2f46130-5a8f-46b9-be3d-23b29c59c379',
                'f6afdfa0-b743-493d-97da-6291c99cb15d',
                'f7b8fa75-30a8-40f1-9612-cfcb30d93e54',
                '858d9800-f38e-49f3-8e23-e4ba64169285',
                '88e35b00-6ea5-4dea-8674-b7c006809a6d',
                '7a3270e3-f205-437f-a870-ab66f5b75c1c',
                'ef02d781-40a0-4744-b6fd-7cee5f50e30d',
                '5cceb9bd-6ecf-4ebb-af29-f4049a7314fd',
                'f1bc9951-9be1-4a00-9e81-73ab09051129',
                'a22338a1-0bbe-4c1f-8bb7-f7c8d003cc3d',
                'd3d75bf6-b08e-4d26-aabc-4660aecd3482',
                '6b4cc1d3-09d0-4430-932b-e4275aaf6780',
                '514582f8-4247-4aac-a24b-1a5c87ad2c11',
                '231ff837-fe60-4e73-898e-1d38e72b912d',
                'caeb61b7-780b-4273-b212-285206be0352',
                '74002fb7-a929-4f21-8929-b9e6ad0673f2',
                '87466658-f332-405f-8dc9-3e583955ec54',
                'f11188cf-6fd6-4176-81da-815cd399afa5',
                'faca3662-4dd0-40a6-88b5-d3a660948804',
                'fd640751-6be6-42a1-b8a7-96f727340b1f',
                '2e753a1e-7edf-409c-8062-c35faff9f2df',
                'dac906ca-d4a7-4e93-b75f-55a043d79d7c',
                'b781749a-3cc1-4fe2-a4c3-f83d57bc8e43',
                '0f379c27-eb0b-4027-a09d-6878cd60d901',
                '18bb6e0c-0e36-477a-ae40-a22e46a1a792',
                '686de52e-9277-42f4-bc82-31b01213bc09',
                'd02d34da-1528-406e-b1d7-de51bc9ef907',
                '8ec9d4be-d09b-4229-8ebe-b3b9ea1e7c38',
                '6e347301-c034-43c8-9867-3333751c517e',
                '1041133c-eb9b-480d-9ece-3f81604f8beb',
                '4882b5bc-69f2-44bb-9ae7-08b720184cc1',
                'd9b01531-f706-4088-ad5d-4f699032537b',
                'a58a1d7e-3d01-42a7-b288-5ff52ed88f64',
                'f1bd3289-dea5-4c4d-b2a3-be69d6804eda',
                '85fd802e-c7b4-4bf2-9ce7-8a9d1418de63',
                'a3b8462e-0684-43a6-9686-3cdbde1273c2',
                'bf4405af-b019-455b-9c66-e3111c27da73',
                '479ae27c-43b6-494d-aecc-7791bdec3fad',
                '52c6e067-3c92-4c5f-8854-7a282039872b',
                'f60fcb20-2658-4a64-8b10-f9ecc70fd6f1',
                '4b803674-b44a-4cf9-ba77-e55a8464ce2c',
                'c66916d7-7923-439b-b0da-326a09e35e4d',
                '8aef5b74-f153-49bc-a6b4-12f2a022d030',
                '4d209625-0ce7-4a45-a210-af51c23003aa',
                'f5eb16cf-527f-42cd-813d-7b7356d9360e',
                '2b19b816-8dda-4a2a-9f7c-d3297e0b32c2',
                '9aa8fbbb-af21-4bcc-a2a7-753cbe99cc12',
                'b7c6010a-e4cb-4cda-a120-b812ee4f5756',
                'e027d30c-8a54-4a0d-95df-c8666f540889',
                '40db1e3a-9837-4c01-9de2-fe22e2cecdeb',
                'eab0f733-ab3a-41bc-8b69-99f350e18d2c',
                '4f0bda9d-fe2a-4ae2-b3aa-4d00f7466e9f',
                'f594196b-106b-4fff-a892-ee41da656afe',
                '46a7fa3b-6214-48cd-90e5-41b924764dc4',
                '84a8dd86-099a-42d2-9f43-f46ff689d065',
                '7f6e5f0e-1097-4bed-9c92-7a73a8f3ddca',
                'ad98985e-d718-49cb-aa8f-91516c4958de',
                '045ff4eb-b2e8-44ab-a6d1-8d0498ac1531',
                '5cbc638e-256e-40aa-925a-9afd96602266',
                '196e61e9-6c95-4a0f-bbac-d904a84074d5',
                '19bc701b-b03a-4310-aa4b-5656aa294a4f',
                'a70a16b8-f9ba-43de-b6f6-386eca7bfe67',
                'dc879a5f-f9eb-42d0-9714-5163d65f440b',
                'da751de0-956d-4704-b35c-17553b67b50c',
                '78820a89-9203-40fe-9aec-6e124e5c140c',
                '2d7a111f-9a1c-4087-b8b2-da736d22bf4a',
                '23ad9502-08f5-43f9-b64c-af05f834140c',
                '7dd8ec94-d386-4ee2-b647-4cdd99fec4c8',
                'c16b4571-2375-4883-b54f-45f1bd298646',
                'f8522a36-68b5-4951-a268-5ff9706998bc',
                '4b305ca6-2325-43f8-b823-ab2a66e0c4fe',
                'ce24bed8-04a5-49dd-9607-454735344983',
                'e920bff0-1458-4993-998b-2bb0d284a3ad',
                '52378c4f-9edc-40f5-8b9b-d3cf0a8d1e17',
                'd73a58af-5f23-4a5c-8dcc-4f454281adc1',
                '5a4b0af4-47d4-40e4-8a9a-90f730d06cce',
                '706c0095-0f22-4bcb-ae48-fbb9d4d79a01',
                '478c31c7-5aee-4f82-8d6a-da3198d00eab',
                '7da9daab-0fd8-4755-92df-73805751f2fc',
                '640f2a70-8465-41a8-8b8c-1ed5b5af11f0',
                '3b08938d-3684-4a90-bb02-1d0691dea9cb',
                'bbd7b8df-0bea-4006-b2f9-35c0f22f6edd',
                '8be5d64b-09c1-4b43-a500-128638d643bf',
                'd2aa56e6-9e96-4d2a-823f-9a306904fe0c',
                'fc3970ab-1c49-4b50-ad2d-97a0be1bc94f',
                '8ca4b5aa-395a-4d00-8a5a-4a0e4a7e1e42',
                '3aab5bcd-637c-4a7e-95cc-136ffabfd17a',
                'fedba8ae-464a-4c21-8c27-592b3a4acd9d',
                'b052221f-5ff6-468e-b64d-3815f2fc05ac',
                'e28e9da5-3561-46ff-8482-1ee0446d193a',
                '3588add3-b2d5-4308-b2d4-464e99af229b',
                'f9782394-a830-4ebe-8294-53d1d9a13450',
                'c20e88a5-aea7-4ea9-adf3-c2b75984afb7',
                'f66545cc-474a-4b71-820b-cb791627aaa0',
                '34f4577d-07ce-40f3-a74e-bdd599a5dc54',
                'db6210d8-f8bc-4187-a9d0-de4db17428be',
                'a35af03f-bd2d-47c3-83b0-ec89d23e97ae',
                '9001855f-85ed-49ca-9e91-96f332b284de',
                'ba0acf96-c8f8-46bd-8b37-ca6f9e3e3c4e',
                '678127c5-1b42-4156-a77b-6d927ee69195',
                'edd7512c-86d6-4871-9578-ce193077f2df',
                '9741daf1-c03d-45c1-997d-d4ac00de8523',
                'fc7521c0-94db-4b21-8145-4e3e0a934fa6',
                '43a77837-04df-4ad9-afc3-d1eb13b2b562',
                'e5b495fe-4040-455a-a603-2a0aa3ac04a3',
                '24e5a9f9-04f8-49cd-a403-ad40a3aa2e78',
                'd0a53446-fb1b-4cd2-97a1-79c43083690a',
                '1ac19c70-c03c-4bf2-97ab-b959bc584cba',
                '919f575e-1c82-429d-8970-03d180f890aa',
                'cf021cb2-e9ff-45ec-842b-3c07ab679eed',
                '3675866c-1356-4323-9a91-9b6119fdfb92',
                'e46451e9-6bb9-4e38-9e21-7c16ede39f0d',
                'bcb0ac5d-2379-4270-9dd8-52211f4f659c',
                'bff8d28f-a1fd-4493-982a-d2357b284fc3',
                '83fed01a-34e1-4f2f-aa14-bdd8c2a4fc6c',
                '6c6ba42d-6a76-41f9-a715-a2c5c606525d',
                '0ff99199-16a8-422e-b294-e83c719e2ce2',
                '642166a2-fa47-4e0a-93c7-462b180422d9',
                'ce429889-2d1f-4d77-a182-640cee4692e4',
                '78da018d-8457-4530-b71c-c200e887e655',
                'd76b25ab-097e-48f8-ad35-6b590292f5a7',
                'f0e532e5-b565-4e6e-a2f9-8f50a5545ea9',
                '085f4bd6-c41e-41c7-94ef-135c476d9443',
                '145a122d-749e-473d-9f5a-e4714778971f',
                '6d89b5fb-5de5-4ec1-afc3-f4afa955bdea',
                'c0bd8964-8712-4683-8dd3-c2d53bf77291',
                'dcb0d438-fbf9-4d47-861c-eb40983c725a',
                '0f96dee9-9049-4e59-b527-d49cce62bf62',
                'c5e99d1a-5094-4f08-9f0e-305ee232aa95',
                '9ec56484-b2e0-46e3-8467-87465f8bc3d4',
                '803b0761-3ff2-4b79-89ba-79a51a2d11a8',
                '47e9544e-9b5c-4a7a-8e24-694ba28f0ec8',
                'b85d7e41-c01b-46f4-a04a-e16dc11a7886',
                '8d55f88c-2dc7-4547-b4f1-5cfab712f24d',
                '7d0d6cb3-09d8-4893-8eaa-e7337d1ec9a2',
                'bd4e7483-5f4e-4996-8244-daee0bfdd99d',
                '38a062f0-ec7c-488c-be11-3364498cb554',
                'c8adaadf-1457-4867-a26a-8efceb776c60',
                'a79b5f0b-dfea-4cf2-aedf-db5cc8edc027',
                'cc621fe9-19c0-4415-b673-abe96979ed8c',
                '2b33b198-c4b8-41ee-ae25-4227518420d7',
                'b9e48d7b-cb44-4250-8526-cc8405632ff2',
                '84dc70f4-9d8a-4794-ad86-d83a58ee5e3b',
                '5eb8f965-9778-476c-8244-65eb5c15c3f3',
                '2acde32f-79b7-41d7-b975-f64d19778f4c',
                '76b8d391-9352-4b0b-a96a-40156304f083',
                '15baa526-5a44-4b70-8be0-2815a15c4eaf',
                '6eb0599c-ea5e-40a5-8baf-0f42ecd368e9',
                '66acaef0-b0bf-4695-a29f-be48484fa5aa',
                '5cb2ff96-4a96-4c5c-993d-b96e336e4c0a',
                'd6a91a1a-a48c-4f2e-ad06-9442becbe5b9',
                '6fc7ac75-353e-44e5-a427-d981d6e3b324',
                '0e4432e1-222d-47d9-8b92-ee5665818576',
                '8d9a60e3-9275-42e4-8416-1bbcbd2abc06',
                '7d44d434-21d8-4fd5-a291-94f3b9bfdd1b',
                '908f4269-9d35-4146-966a-e592f2f6871b',
                'fef561be-f7e2-4200-a1d2-5f6fca185aa2',
                'e13bb71b-df01-4477-a218-1f0abc35120b',
                '27520a33-fe0e-4ce0-a2bf-abd1d4827cd2',
                'd56355f5-efa1-4f03-962a-de34ebbdf243',
                '40bf9db9-8d3c-4960-b924-b6e7d1509d20',
                '6c0f3bd5-8bc7-4570-941d-498744f25fce',
                'f9b68450-d5a2-4d59-a0bc-1471161c9d33',
                '57ec3d30-9741-43ee-aadc-300980e9d58e',
                'ae108565-dadf-43c1-b3ae-10f4c30c06bd',
                '8d8a7703-aebc-47ea-bd51-0e991a69e2b4',
                'acc3fb6f-ae79-4aa4-873e-4cc619577632',
                '8aed781f-21e2-4324-848e-3ff49397556f',
                '49c97314-3e4f-4928-ab9d-da2f2163aff8',
                '4e3b64e0-ef9d-4edf-bdf0-2aac42bae639',
                '087f1b58-e20d-4a6e-a214-bd92e50fb3ca',
                'a0128b64-6102-4518-a3de-c97f6153de78',
                'fcfa8ea8-3593-461c-b0b3-51f507916855',
                'c3409d17-abfd-44b9-8c3a-a7652f93a0c7',
                '41144369-92ef-4146-892c-49d7a8fc71f7',
                '06016c2c-e6e0-40d7-a4f4-7460a73938c4',
                'b801c774-1f9c-4c6b-b580-68358c86f3ab',
                '435bf18f-447f-4f7e-8289-e739e58c6f89',
                'bdf8e9ab-7523-40b1-b5b5-94d5de1486e6',
                '7d0dbfdb-4d19-44e7-a4ae-ab1664e1e674',
                '7bc2d824-d995-4ebd-8531-a6d3e8e81a6a',
                '001b47f0-bd66-491f-ab08-47afbb303879',
                'dbe8744c-d2b0-4710-95ab-1437b7d1cfe9',
                '3f74b145-95a4-4dca-828c-0ac3404d4054',
                '7e4a3778-2679-4030-9ac4-0d203f6a7ed4',
                'a22bb077-df35-45fa-acdb-8c3e540986e9',
                '715c01fb-e7a1-425e-aee4-0bb4d81f8f73',
                'b12eead3-8cb0-4b02-b590-2cb5e22f8f33',
                'c6ccc5cf-2c42-4e49-bb13-d5d3eb57c566',
                'c1a33daf-3be7-499b-854f-4d63f72dd112',
                '5ce73a10-a34d-44d8-bbb3-6a58dfb21895',
                '5e99470b-357f-49bd-ae83-583f0a498448',
                'a34beaaa-1a2f-418b-be5d-e44cb7808b5a',
                'fc2167b0-55cc-46f8-ad1d-de370f769239',
                'bf7bc060-6d4b-48f6-acd5-b55a6304dbbe',
                '1b12d436-e1a9-4304-bb34-f2777d4079c6',
                '48f2ad69-f1a9-44d6-b371-9e12caaaa721',
                'bdea570a-91ee-42a3-a3cf-01aa2e5fc75a',
                'e09fc904-ff34-4ae5-b9c2-2edc13c63d53',
                'c0331a7f-1953-42be-84e7-e675b5dd4aae',
                'c2ea0849-4ee5-41af-8fae-ef1b219b9f35',
                '7c50e2db-67b0-4f14-9edf-49cb7fe4d8d8',
                '96a74bba-b767-4565-b42f-d9f4fa46284a',
                'c79c0114-48bc-41be-a4d2-11cf34df7026',
                'b0390e30-09a8-4544-9ef6-f4b6240cbe96',
                'd7c018a0-e99d-40dc-bb8d-e83f91fdf1bf',
                '6f5ee421-91f2-4ec3-be3b-af213dc80164',
                '0fb558a6-b93f-4525-91b1-9d88af041a74',
                '9c84afb3-73c6-4e4c-a82f-15ef4c9e9fca',
                '342212eb-64e3-41d7-91db-ea42037baaf2',
                'ea63ca83-e1b3-48c2-86b7-4b36497aa5de',
                'e0bcc276-82fc-464b-b6f3-491850447828',
                '40504745-604d-4ad4-91a5-50dad5d546b6',
                '444ce2ab-be89-46b9-a920-8edebd90e27a',
                '17ae9103-7908-43ff-b1be-e36ba3542ffc',
                '4adee268-99ed-4cab-9b59-e5c282e37b69',
                '64547bd8-9561-4ba1-a598-f1c1c3edcf99',
                'e5d67d11-48b0-44d6-a9e2-b5322384ca3d',
                'c1ba3e76-c240-4255-bd09-b9baa9984d1d',
                '1b22a36d-4b68-43e9-90d9-80bf7b5fe75e',
                '19fc059b-667c-4f3c-89b9-48986388b6ee',
                '67be7ffa-47f2-431a-9d55-5b49521ce715',
                '39a22a26-1d92-4567-ba0c-b44e2b14a0fb',
                '3c3fc74c-ca7d-40d3-9e2c-66cda33cff57',
                'a3f3688a-1969-4f61-a06a-7aa484f64af9',
                '067c83b4-8770-44a9-9da2-69a8993b2979',
                'a24d586c-b506-4bc4-a3ea-645ef011a8d8',
                '4ee403ea-308e-4571-9bed-b468ede7e3ab',
                '61fa28cb-2041-4a23-92ec-6ad89f04f792',
                '17a3d112-f444-49d6-8724-17f416233acc',
                '32e991b6-d37b-49f2-9d07-3f1674215848',
                '9bfe88f8-06c9-4bc3-aa2d-9b5233ef9e35',
                '7cb2def0-e2e4-4e32-92fe-424469f1dcec',
                '073b4de4-0880-4dd6-bbe9-fc54a45be7a7',
                'dfa55c1a-07bd-4134-8850-276593c96972',
                '75dfa485-cafe-4cc6-993d-d084e8379f38',
                '282a4e19-e95a-418d-9783-2887f9c69a97',
                '22c7a30c-07d8-4783-b3a6-7e9f7bde059c',
                '96f531e1-90c7-4b66-b311-1e97b4f98ee5',
                '9ef54a24-88ca-48fd-9853-16be97dca857',
                '66426588-8277-41f6-acbf-ac46e6215835',
                '09091595-22c4-4bab-9b3f-04bd26859845',
                '42d300f5-d50c-41e2-a49f-ec4a90ead128',
                'a76e660d-1da9-4762-ad94-d8b5796ebcbc',
                'ab381a45-fdb6-4f42-9091-6d80000bc792',
                '5ea3fd4d-94d2-4cfc-9dcd-efeb19a07989',
                'e438f5d0-141b-4305-adad-1a4341209a5b',
                'f4d3121c-8426-4dd5-aa7a-afca5090ee2b',
                'c96f7d19-8270-4298-a7b3-b087f7879b34',
                '555e2320-c70a-4085-86a1-e3a9bad53c5f',
                '6afb99a5-cdcd-4dcf-b9f4-43522188790b',
                'df21f938-e6d0-4139-849f-d7b12efb722d',
                '9e9efc65-e73e-45cf-9623-d9d24c796ff6',
                '466f5d70-0309-4cab-a918-79ebbc872d17',
                '6ddd7f81-e562-481f-9cff-1470278b29df',
                '37a80dcf-5bfe-4ab9-acca-8f76423e71de',
                '54415050-b75a-4ffa-abd3-41d7fde60869',
                '09d039d3-0b0b-4135-ae21-560bbc792595',
                '953cfa05-4183-4ee3-8184-81e8123cc9a6',
                'b307ff21-01be-4e4f-a4f6-c9abed18118a',
                '24d24753-f18b-4168-9812-11c3b46e5c33',
                'ca9588df-4a11-4030-80cb-81e1e52f0edb',
                '915424ed-2c1b-4065-9752-8b1ecf61232a',
                '0ce8e96f-a7c6-49d5-8f28-01b25c9a31a2',
                '3293d5a6-1708-4a44-9846-2fb30666d9d8',
                '5336c479-8b74-4350-92a9-6be270724479',
                'd64992aa-00f4-400b-be1f-fa19eab50f21',
                '51d85a58-fd7e-4642-9101-773dc5a13433',
                'dd52a256-3a0e-487e-9834-c6174d16a01d',
                'd65d7b50-6a5c-4004-a392-5fe52db5051c',
                '0ab4490c-7433-42be-8f45-9c9a9a448cc9',
                '48aa30db-41fc-4a97-bf49-f30963cfc7e2',
                'a3aa022d-d67a-4045-97a3-5ed02df55a53',
                '903cfffb-a1e6-4cf4-bde5-13b569eac6ca',
                'a7d9ea97-7c4e-4376-9a2d-9b6fdf570cc7',
                '24ae7f14-9be4-4bbd-accd-539e28e646eb',
                '4a4b4bf4-cdd1-4db7-927b-967df65798fc',
                'bbed6bdb-a780-487d-8488-744980f9e2e5',
                '46412893-bfc4-4601-a6b1-cb8b8d8fa138',
                'e0f28698-2377-4446-bfba-9d4a6e31beb2',
                '37b34218-a7e3-41fa-84a2-15bc98e91183',
                'f9557831-5aa8-45dc-afdf-b1d26f71730f',
                '3a897212-a3e3-4acd-9bf8-db5279ef5bef',
                'ff480dff-082c-42df-8101-df626a4fde00',
                'acd6adfb-e45e-4e6f-9fdc-c8ff5960cd53',
                '4fa87faf-710d-45c5-9738-ad543064dcd2',
                'da1ae223-da43-46a8-bda7-409ac7b893c8',
                '662ee114-20c7-4ec8-802c-eef26bf200af',
                '68cf582d-bb29-49c5-a9f9-41c8750be466',
                '39930449-155a-4244-949e-dbd6c390a9b0',
                'd00f08d9-15d3-47dc-a29c-f129315a5c56',
                '55fc7768-463d-446c-ae2f-938af1676050',
                '1dd7139c-b927-4406-8cd5-7d0ebd8c9413',
                '533d7019-8eec-4680-97e9-dade40b10621',
                '57f22ce2-7ad2-4e84-b5b0-029d9d999c86',
                '6812f6b4-a230-4f0a-8549-16911cd2baef',
                '3463e363-7fe4-495f-90d7-8c374ff88108',
                '7da4d498-153e-4d67-9268-e68b64631658',
                '54ee4853-236d-45f7-af9d-c20e43e082f4',
                '8d170609-e42e-4b88-8e01-d8a7a4fd7851',
                '3bfb05eb-3c82-416f-b7b4-cd031d10bb3d',
                '9b3fd7b8-eb30-4827-b8e2-badb5f84f769',
                'e1bb187e-80b8-40e9-91cd-1b64e902f90d',
                '5ad0d5d9-f0c7-4261-a2ca-0c271ad22600',
                'cb79eab2-fd93-4a1c-98ee-d9539e3e4765',
                '175917a1-f5bf-489b-9d67-98237f974039',
                'a2d21051-8502-4a65-b1e3-d737bb091148',
                '3f5a311d-564e-4516-8ab8-f3acfe000eb0',
                'f30aa160-2a79-43e5-b4f8-79d44b3bd9d7',
                'f1f05a67-3f35-447f-984c-da7ec1c41ade',
                '68c4a294-2989-4051-9b7b-68e3b3739b88',
                '67130417-269b-42a3-8197-fc6eb2bf4fad',
                '9d44ffc0-9c5b-4442-9f5a-53f022b7e857',
                '926fceb0-251c-46d7-b159-42eb6f215501',
                '913a2186-d13a-407d-a1d0-73af29e05f29',
                '178b9ca7-657b-4a31-b816-a5330988d52e',
                '8c305a49-a001-4aec-ab16-1843a89e609d',
                'da1f386c-b155-4070-ac5b-af07f20ac6bd',
                '65753910-d497-4adb-9c0c-ac00b4d438a5',
                '2f2fafb4-7b98-4972-a579-0d372ddca967',
                '5b313c37-f28f-4c40-a110-88eec08b8beb',
                '8acfee0f-a660-4649-9d6b-d4e93171891c',
                '2eb760e2-94c6-48e3-b4a7-8292d8c17ccb',
                '81ff2788-d133-4ae2-9be3-f7b46baa92d4',
                '047d25d2-8e5d-4091-bba7-4cece1436148',
                'bc4013b3-5a8a-46ec-865d-439cef895d12',
                '7186d3dd-e32d-4972-b038-1ea366b1c118',
                '64a14575-b453-4b4e-959e-0e4ba6c58806',
                'e5a1d402-fd5e-428c-87a1-ac810c456f1a',
                '388abec2-11e0-433f-b8fe-88dcd4bb408f',
                'd60ee41c-aba9-4572-bd92-e0c3d79f7a2d',
                'cd154f52-773d-47a0-acde-c924dc6d37ee',
                '29368232-c443-4655-baca-b75b1627ecfd',
                'ee2aa2f6-2ea6-4a04-8e26-284afbecd3cf',
                '932e29de-4bb7-4f8f-8822-c7731bc30571',
                '04dab086-c321-480a-b9c6-78182b3e9db4',
                'a5a0332f-1752-4335-84b3-cd6ceb7e46aa',
                '9e5b96a2-4b80-4491-b020-47bf79b2287d',
                '286b810e-58fc-4343-a45b-50c9b9926b55',
                'f19625ac-b584-44e3-b66a-cca14cfd6011',
                '00aa0765-a365-42ee-b36e-ffc9c258586a',
                'a1de10bd-44e7-4f7f-bf96-f289e723109f',
                '6105a633-7d4d-441b-971b-446d9ff33466',
                'f74a8eab-0503-4b15-a4a0-6fcb1d1138e8',
                '92c00dcf-56de-4162-9709-3d0513969718',
                '60b2b4c0-0306-49af-b4a2-aa5bc12daf9b',
                '96855970-17ed-482e-a975-84734dbeaae9',
                '0a73de52-0af5-46be-9d3d-ea3d74f6a844',
                'a4a9d0ac-e82e-4f8c-9c1a-ce8254d8dd29',
                'ed6a970c-c909-4ca8-bd9c-367fc2293ea8',
                'f8fb4015-85fe-4721-95dc-72dae8750382',
                '33dbd582-3633-4a0c-8564-9b14bfb4f8ad',
                '7e9f7b51-3c8c-463b-a60a-50705710cac4',
                '176d9941-bd16-4b10-a94a-9a6f149dacf8',
                '2bdc846e-08a4-4be1-aa69-e97fd49e2ca4',
                'c897c6c2-049d-45f1-8d81-c386592db92c',
                '3e9403a2-0961-42af-bf7e-51ab52f3a7f6',
                '0911b5ad-9a68-4868-a8be-ee3e4f8a546d',
                '712eaa1f-8798-4e64-bdf3-0e0df55a16f1',
                '4b9e8e90-7f60-4fe7-ba4e-74ad4fa070ad',
                'b50b8514-3b6c-440a-9541-bbce7db78343',
                '7d3510b0-6898-433a-a71f-b47d8634f3d5',
                'f2b2645e-1d70-438d-be6e-5edf6e8d0d0d',
                '8ea4ae2a-9464-4cf0-af2d-8f79009f3b45',
                '745b18fd-7358-4276-8d04-e9792e64328c',
                '443c3a20-4e17-4249-93d3-30fd971f4c58',
                'bb107ac1-b864-4e06-8cdc-a03f5dde6336',
                '0f836547-e39a-4eb2-bc59-093850fe3a36',
                '4aef97e9-6bf0-4ff3-8db3-9b26b0e04e62',
                '1dbd338c-253f-483c-9c23-1c7d24d33fb7',
                'bde9f3aa-fbf2-4bc2-b338-16e408a3c0af',
                '64076e4f-88fb-4c4c-bea7-0ed80310a5cd',
                '1bae6592-b75e-4c64-9b40-3a938ceeb297',
                'ee7ba505-9906-4bbf-917d-8edae8e1d2a6',
                '58c1cb64-9ec1-4a92-80c7-821ba31d3127',
                '41d285ff-1284-483e-86a2-970a68ab5e97',
                '97712f63-fa61-4efc-81e9-ed0eb47fec8e',
                'c6ec7130-8222-4ba6-b826-7147cda76106',
                'a87844ab-01ce-4d1f-a917-484da117eabf',
                '7373e8f0-bc31-463b-85fd-1a1d5bb18ea5',
                '57b82930-31d6-4b85-bf9a-eaa4041c580c',
                '14e7dfa7-18d2-446a-8410-ec0e17db0c64',
                'ba758794-3971-4b2d-a771-ba49c6d0c98c',
                '02fa4b57-7f13-41bf-9703-608b3277fadf',
                '14882703-eacd-464c-adff-07dd02a847e7',
                '181a05d5-acef-4c12-830a-0317715af569',
                '6cde8d82-1402-4b8f-99ef-6d83c137bb95',
                '7ce7e438-9130-4f2f-aa6c-26c5aa71cc27',
                'b115b61a-df5e-4583-95d1-d7ed9fe8f5ed',
                '31b34714-5ee5-4202-9853-30db11382e5c',
                'a9807acf-721c-4933-8c0a-a04ebc04d5bb',
                '50ee2a9e-6992-4535-8a11-ec7e1b1bc3cd',
                'f32a480a-84e7-4d55-9950-54c5076a27ff',
                'f235ac22-6422-4a99-9393-2c0eaf14c108',
                'ba030d68-52b3-4b77-92f9-a8708d6140f6',
                'ed862208-f3aa-4720-90ca-e98e57488e29',
                'b8afe4f4-16a5-40db-aa53-a48abc780e68',
                '42a6b60e-9b3c-4e34-a915-0d4629b50219',
                '6dca74bb-0321-449c-ba1b-eb535398dc77',
                '8a228f45-6bd8-4235-8207-f807f605e7c1',
                'e2c4beb9-5bc9-47c6-87a8-68b21d626318',
                'f3b2ced3-a0eb-4748-917c-64cc7ab384d3',
                '6828726b-7c70-49e0-825c-5e84bb1577cb',
                'd7368a28-0908-4f2e-9496-95e15ede63e9',
                '8e1c9e1f-1118-40d0-b3e3-7cc3e9e924eb',
                '56d4fa63-298b-40cb-9ff7-3de08babce9c',
                'a08098af-7237-47e8-b030-bd05556e8ce5',
                '4d3d024e-62d7-4d35-845a-b47f8092968f',
                'e6d7b6e2-2ef7-4d4e-b9aa-3411c078d466',
                'aed9b818-236a-49a5-b2c0-02c6651e80b0',
                '0ebc8380-6f2f-46b2-b885-cf9af6d8e747',
                '916966f5-35eb-44b9-b3bf-11c8a3e418a5',
                'ee066767-a619-430f-88bc-37c11dbda72b',
                '883a1e25-5e2b-4f3a-a858-fdbfddd21ce8',
                'a3602314-6cad-415f-ba48-35ebed084d76',
                'b3a4007c-733b-499a-ba9d-cf13795adebd',
                'ce258456-05f8-443d-8223-7e04376c9e6d',
                '3e5cfc78-a112-48b1-9dab-be636b55d7c9',
                '93a22398-edfd-4b35-86a6-4596e8335aad',
                '95ecf916-af27-4b81-bb97-bc817e43b33f',
                '9aa16f3a-6f16-4ea9-ade2-9dbdd951b331',
                'fba348d2-b317-477e-92af-c19855255c1c',
                '41263db1-a49d-41d9-97fb-85a1cd1a416d',
                'b8144735-b153-41b7-a04f-f9e45dc69c6c',
                'b65c006d-7b75-48d9-9209-38cd9fb1f1aa',
                '0e27d44a-16d0-4938-b546-24b565c01cf0',
                '3c1f3b7b-036e-46c2-a0fa-444b36af9ad4',
                '218bed9a-08f9-4407-af8c-81d1c0249100',
                '28a297d1-49d8-4ab8-abff-63da297fc7e5',
                '59710b7a-8930-4116-b201-95e736677e21',
                '92c8db53-a0d5-44ac-b720-31109700b1a5',
                '8472c317-8612-44f1-beaf-4493c81601df',
                '9dcb4082-3451-4464-9d03-6deddc21f0f3',
                'f2f2ab14-4fbd-4ace-b131-771a6fd68bbf',
                'eda53190-f7a5-46e8-9e29-83b782838ba1',
                'fec219e6-e527-46fc-bec1-7d364cd512b7',
                '4f7f6c7d-9fca-46f3-8a89-649caa22ac56',
                'a7a90d31-8686-4a5b-bd4b-5376fd6aa9de',
                '83fb36fd-37a5-4534-a185-4355965c602b',
                'd04f22ea-8fcb-4908-ab79-75f8c7fcacc2',
                'c5bdc6b9-0604-4f95-88d7-cd583f8074f5',
                '90a26b2b-2e14-4d88-947c-f7405bfd459b',
                'e927baae-4f29-4587-bc97-84b5afc4d59b',
                '8f79cc97-82ab-4776-91b6-92c2e07d3427',
                '546f1301-b97a-4833-a761-f2d945ee5e26',
                '7dcf89ed-3ecd-4a9e-bab8-db861e77b007',
                '425a5649-eb0f-4ab2-a133-9101b5ea31b3',
                'd91bf829-1861-470f-9d10-612086523cbd',
                'd5e85771-db46-4e54-8b44-d29b68ea44a7',
                '96f61de1-baab-4a4d-a785-ff68ada97dea',
                '80789331-c99c-4731-8d7c-ca6f90e00a21',
                'c112ab7d-40ad-4aed-b85e-0e642015f3b5',
                '02b91bee-66d6-4f8d-85af-a830abea438e',
                '7a08a393-d5d7-4eab-ac19-a2a2acef9eae',
                'c0398add-32e3-4e2c-bbe3-fb0d256e9a6e',
                'db3ca8da-67c5-43a7-ae52-1fd45ef8cc08',
                '81f81fe8-80e2-4b13-9cf9-bdbdb21ed3ad',
                '2ca9fed0-98cc-411c-9fc8-603978d3d185',
                '4b2e5e24-f902-4e41-82b2-e8d19377b434',
                'a2b87685-383a-4c41-a858-15eea86ad150',
                '4eab946f-e3f8-4679-96e8-c7f9bf5c6fd8',
                '4c0c6e94-bc47-4a7c-9e61-6e290a647b1a',
                'aa4e65a2-1ace-4363-ac62-5a07fcfec65b',
                'c76f8b25-599b-4471-8419-5bd90b31fd8c',
                '9138525f-6bde-4964-8637-a03707c51e3f',
                '8615d464-5b61-4d97-a239-87d30655332c',
                '6e889b6c-c57f-4fbd-8760-1907676528a2',
                'e8017522-ac01-45fa-ae5f-f8bc84fedafe',
                '88b63ee5-c70b-4188-9306-0d842a7674c4',
                '4962185a-0452-422a-ac3a-8db392806398',
                'd59763e1-6437-4783-ace7-8fe0d9c9b181',
                '3b9a1ecf-12b8-4457-9e79-68b509f83425',
                '797a7bef-cf4a-41f9-9695-a50d1f342c17',
                '93b67c77-d592-44fa-b555-406c7c010f2a',
                'd254c605-31d3-4424-8a49-4f9b71a6d1ac',
                'bdb354bf-0e63-41e1-a8c2-146f8a30ce20',
                '1fbdbb45-1d3f-4bbc-9b52-f78cb32c0d7d',
                'e1af15a1-1add-465d-bc79-c96211689a9c',
                'c13a8021-a062-4336-9207-8778b0820b83',
                '37e6aede-edfa-470c-b2e8-707cffa8d750',
                '121ab625-5f78-4a14-84c5-ffab465f6832',
                'aed64a0d-8666-4064-84a0-ed44f1108151',
                '3991b4f3-56a5-4816-af3a-af6c788b35dc',
                '960d0486-7564-446c-8058-b26312359771',
                '13a270cb-d464-44dc-ac85-c8201a3912b9',
                '23edeecc-b6f7-479a-8cc7-e5bd5079e30a',
                'df6c1af4-2d51-44d8-9d74-b43e943859fa',
                '115233e8-dda2-4503-ae6c-5fe4f83ffa75',
                'b25c72cd-7917-4bd5-a048-f1ba9d96cb0c',
                '7475f3b5-03df-4e95-8439-34c90ff80e98',
                'ab5939f8-dae7-4e45-91ef-0c06e7c58df9',
                '64719c1d-cb38-4b48-ac66-69ee4ee0fd1d',
                '80ea5b80-0524-45a0-822e-164b05ff4214',
                'efe4989e-84be-4801-9fac-d3c360e92758',
                '1288520c-d9d7-4a28-9624-ef3ce5712de5',
                '3eb304e8-dac6-4caf-8778-c2d5cf99835a',
                '46653bc5-de13-446f-bef0-672cb252c6b7',
                '27f80195-1c37-4d47-9910-3c39003fa5d2',
                '1cc9459c-a30a-48f4-a555-15e2c1154dc5',
                'dfdb2a5b-6327-46c4-8129-702d9cd6749d',
                '14a9e381-6630-40ab-828d-86df05930078',
                '2d8255f4-a783-4a63-b25c-bab9d3279c2c',
                '55014282-f5d9-4664-93cb-aa15ab0bec32',
                '4cff00a2-f262-40e8-bc39-ebbc762bc690',
                '5521a56c-76bf-44c6-9d34-1d7e2be3f96a',
                '1fe20646-66e8-49da-8d08-1ffc41c99c9c',
                'b89b58e6-7924-4e57-ad71-7dd0cd46a85c',
                '1285d3c7-31a7-4b8b-8907-284a36762b08',
                'df7f1607-8541-416c-9592-ce1eb81ea860',
                '81a30267-1c20-4b19-97bc-115a019bc6ff',
                'c44066e7-2f73-4cb9-8ba0-4cfc2cdbd2c0',
                '65df6639-16a8-4a92-84e0-44dcb54f66bb',
                '557ec1d9-7d2e-424e-ba5d-f844c04a6d34',
                'ff9f1e1f-1f45-4e4f-b96a-4c93a157eec1',
                '8965c731-cf2b-47bd-8c1e-95de388342f6',
                'bbfbbab2-2632-4207-96ca-91c7112c8671',
                'df97d48e-f6a8-4b6d-ba05-c7296d36bf36',
                '63fd2f0b-aec2-4765-a991-bd57f1d8dbe0',
                '512a2dd4-111d-4df8-977e-a5963d051a13',
                'f7eca8d2-7e1f-4b92-9ab3-3853dcf4fe29',
                'c10499a0-706e-4a86-bab3-c3858fe9e067',
                '7682bf20-1f4c-40bf-9056-4e8ae515191f',
                '3a7f8e5c-a8c2-4487-9d45-633ac9b2d326',
                '9998e42e-ca42-4d02-a5da-98bd61c28926',
                'a204183e-c3ee-44be-87a0-f332bc9592f8',
                '4caca4c0-e693-4978-b0ad-e7b33c1a5381',
                '9840fa0b-46bb-4338-ab6f-7327b89ac601',
                '31307b2d-5fb1-413a-a222-23dec83d51b7',
                'c4678524-3c70-4012-8784-6868945d9436',
                'f2c3b2a5-6fbd-42e7-a57a-4f0783ceaa06',
                'ea8dd78a-3153-4a1c-ad4a-c591ac7a29a6',
                '8ee086d3-7446-4a81-9718-421408bea040',
                '847d9c4f-b655-4c4b-b941-b7223abcb8d9',
                'a668ed4a-767b-43cb-b7bf-f67c7933afc4',
                '2b41730b-1db1-43ca-a570-40c3c54546a7',
                '11989d22-494c-49e7-b6b1-4b4a81fa2a25',
                '5c7aa7d9-f521-4fa0-adb6-9c2efcbc7c4e',
                '347fde92-725b-4edf-acca-2e1c822f7429',
                '4ce9448d-eb91-4f84-a8f6-2b4fcceab072',
                'e7091ed8-c070-4c3b-bbb4-9f513213327d',
                '7d298329-6723-4fe0-aeb1-2fe70c1f34c1',
                'b29ceeae-ce34-4d05-99a0-e589560da87b',
                '8719d49a-39e3-4d9e-9d00-9eb82fb95ecb',
                '6dbe9c19-e9d2-44a6-8836-48d953b0b216',
                'c0fdd2f4-5041-442f-9893-ea0286f7b939',
                '8e4d24a5-e085-4f98-91b7-9bf8a449e38a',
                '0f60276e-cfa1-4744-8b7b-6b25ce630208',
                '5236ad1b-679e-431b-92a9-ad93aee534de',
                'eb80a463-153f-4d96-a9de-3e789d63886c',
                '2168443c-a597-46ad-a764-fb145f7a95d9',
                '2aef3df9-853d-40c6-bdd2-81f3e4d3e83d',
                '482e94fa-1348-420f-9530-36525a63a8ec',
                'b7b9e399-a47b-4423-8724-41c2e0455fab',
                'c8311890-2b00-49ed-a530-60392efbf6a0',
                '339aa1c9-bf7e-403c-aaec-3012003e613a',
                '83dbf987-10f6-4183-8734-eb9efeb59f2b',
                '9080f386-067d-4fee-a129-9110c56ed0b8',
                '0dc981f4-61dd-4860-af02-bf55267e8b6d',
                '8bde18c9-f8b6-4672-999c-4b79ad855218',
                '9687fdd7-b352-4313-8d9c-a4f11c257a26',
                'd5fc297e-9757-430a-89b4-fb3cb600ec32',
                'd5cd2824-4bee-457e-bd62-b24cc4c70b9c',
                'df4664c9-282b-452d-ac9b-a067c00eaa0c',
                '1a94a69f-c6cc-465c-91b0-76a52d172bcb',
                'b9a9a98f-1a4c-4040-aa7c-1328505356cc',
                '78fe0e74-9054-4caa-a20a-0c51a3fcb8c3',
                '070a48c3-69a2-4216-a03f-3bcf01ef6f1a',
                '1a65cab3-1ddf-4f61-a222-0bb152185489',
                '10c7040b-71f5-45ad-a146-bd1ee542c249',
                '0aead180-62e2-4dc7-98d6-4be0a1c2a291',
                'a794007e-9d81-423e-a07a-638aae20beb3',
                'd0ca5f7d-b120-44e1-9e8e-2c08b0fa87ff',
                '3bdd4eba-2e75-4dd0-b0b6-92ce24bd66c8',
                'fe99569c-7cb0-4715-89c8-09bcc1d0bae3',
                'b920e4ca-b4d2-4e5c-86cb-12e050a25d42',
                '310c8f77-4210-4bdf-8d8e-2459499d17c9',
                '11f0e0f3-00bb-4f10-a323-f4af348c5a8c',
                '2e3cf4f5-28fe-4555-9d92-66ee0377a9d3',
                'f4c67a90-9d55-4018-81ac-49e34fe07d9b',
                'ce38f682-0dce-4276-b59f-b63e1b92ecfe',
                'f0a6d3d1-7020-464f-9255-32a25e921cf9',
                '79fe389c-ad4b-40bb-a5d8-939f20750876',
                'f857b720-c18f-4ea5-8452-4ff84719288d',
                'c29b66f6-ce50-4a2f-a404-a2c9571b6b57',
                'e3b8ca3e-2980-4bb1-b172-a6757a46666e',
                '2109b4cb-2dbb-438e-aa02-e05bb296d050',
                '26daef15-f67b-42dd-9953-b4fcc12aa459',
                '39283ffa-cde8-4a9c-a05e-6f13191955d5',
                '35293757-10b4-42dc-9392-9cf2534eb384',
                '0b2d83b6-a6f3-42e7-9089-7279a6e73907',
                '47b53338-2558-4668-8be6-872e60d52cb0',
                'fd740c75-b2b0-4294-a2c6-8554e282998f',
                '7880b247-90ab-4e06-90ea-b3850d45cc92',
                'e35b4bf5-76c9-4111-ba61-4b19573c3660',
                'b784ba48-137d-40bc-9a60-f1ac06c9ebc0',
                'ca8e0306-3ec4-4254-8d4e-37f9592d7f25',
                '13218426-ec73-4b1d-8d7d-5adfb328a2bc',
                '7e1f5225-b5fe-44cd-8300-e793e114135f',
                'd94ada2e-bafe-4135-afa8-3360aec91cbc',
                'e8e5d60f-f67b-4508-b0bb-41f2e66fd2f2',
                '59dabb34-ef7e-4bed-ac73-532db3483e42',
                'c67f9a8e-f025-43d3-868c-d401a86764cb',
                '649af929-182f-4953-8242-0bf2c72ea686',
                '642d2110-e3c4-4761-9c7b-f24afb44a5a6',
                '4ca4659e-0471-4461-8520-afd0fd940dcc',
                'e6421da2-4320-4cfe-be3b-03a2f5ae8e08',
                '3864ff90-b5c0-4f43-95f9-3f83893975c0',
                '87b20eb6-932c-4a95-905b-cc7f770332d8',
                '28b920b9-a981-4b79-9aac-f886973ef32d',
                'e6c544fd-3069-461e-9677-cd292f104792',
                'e4203225-ca2a-421e-828f-7c514f37a2d9',
                '26a3434f-440f-43bf-a5a0-35d833b37345',
                'c418e5a5-ee70-455e-9286-8743b5e38eeb',
                '30448c67-1f60-4823-936c-fcec27312b23',
                '9d65244c-e507-4916-a93a-bb80abd2d6f7',
                'ac3f48b3-a79c-4630-9136-1d2e557d4f2c',
                '49c1d0c8-255c-45f6-8586-a7f58b19c30b',
                '05c3febc-1b1f-412b-8e33-51034754a93e',
                '558db28f-61a3-4192-8a33-c18a199c3fff',
                '2c7ac0f9-7f47-4bd2-b50f-712cfcc6413c',
                'e6c26caa-b64c-48c4-bc30-40d6d8033bd5',
                '0af7efce-afa4-4e8c-a8be-3132a876a852',
                '8eee379e-355d-4bfb-9410-980fd72464d3',
                '61f399fe-31f6-4c30-b2b0-f5b62cdfb90f',
                '3eeba696-1bb1-4a54-babb-ae407f4e172f',
                '4db9b9b8-b412-4a7c-82a8-f2d6569619f3',
                'c91df37d-09e7-4d56-bc98-d155aa46be7b',
                'e65d2af0-effb-4181-a22a-cac6a4270cbf',
                '6c1ad2a4-6889-4a80-84b7-3cf317cbd453',
                '7335ff4b-cb9f-4657-be51-e11e0540bf85',
                '37e8452c-5c2b-4f71-a264-a8c72bfca041',
                '97ae7275-406b-4661-86bc-32a4cdfac12c',
                'a8b94011-0a8d-441f-a912-8007c248be7d',
                '50da229b-a9c9-4181-b8d4-4f77423f94f3',
                'd807b74b-8c56-447f-8ae9-ed2335f4f52d',
                'c18a5955-c0dd-433c-b49f-9bf614ba76c3',
                '896a7e86-633d-428b-b4fc-57cbe7f42696',
                '6a7970e0-6039-4203-9aa0-080d7029be93',
                '3f524a41-1669-4a18-8ed7-8228e79b95b2',
                '4062dadc-5c93-4bae-ab79-951f518509b9',
                'c809d2ab-c317-4d2b-bc83-a2a2f351833f',
                '42d3988f-f808-456d-b772-fff5aecc6427',
                '42fad825-e1e7-4c0c-a80f-3b8d208ee5da',
                'd180d4e0-544f-4596-824c-05653af444d3',
                'd70614ee-ff81-4467-bbad-67847f130fa8',
                '6056f622-5085-4eca-8825-f4ae5d3e458e',
                '59044580-8b39-46fa-8ae6-3aa20a6dd100',
                '0555ed53-9995-4921-ae6f-88ec5ec99006',
                '48802f41-068d-442f-a643-e66b03b317de',
                '3bd83552-42b2-4147-a8bf-ebac59c79ea6',
                'c2faa4d4-da04-485a-8d82-7d00611aebbe',
                '1a09ff42-f8c1-4027-ac59-f690941c1617',
                'f0b475a2-7a26-4bdc-b204-8ca2bce48247',
                '07fa6e3f-d91a-4fcd-9d9a-cdc2cb31a64e',
                '17c160ff-cf59-4776-82d4-f6c1978e83a6',
                '7ca57e6e-9449-4530-9d89-01afe4ebc701',
                '9a67a387-ecb6-4342-aa6d-a4583eb3c8c4',
                '6961a23d-fe83-4e54-a90e-4d88e4b25377',
                '39345543-6fd3-4e76-bc24-907aee92a2e5',
                '4b44ab69-22e7-4846-836c-7f971e9e5caf',
                'beb9e0a8-b8e9-49e5-80d7-e9016fc01982',
                '4b0857c3-001d-491e-93f1-d049b01bd93f',
                '574442d7-5b78-4f39-bbae-7c4006f3997d',
                '1df79e9b-6646-4043-9d21-a82b56ccce78',
                '1f301624-9af0-4b40-b686-9faea84c0f72',
                '7bc44052-5b8c-44f0-ac7d-de58de3a34c9',
                'aebf0060-498a-4707-9c54-e3d9baa5d91f',
                'f4cd3307-bd68-48c8-a750-ce6e47345ffc',
                '503a19c6-ea5b-495d-90f6-8533a43ec0e2',
                'ceea8e07-dc58-444d-8b5a-446bc3fdbb81',
                'a4a577f3-522d-43e9-86f5-a88f43377091',
                '268dc0b5-2c37-499e-8272-927d9d68d94c',
                '05aaf026-e926-4805-9c54-45e67a4b1c33',
                'de8a2e45-b817-45a7-940a-73aeec1152c3',
                'ecf46b6c-e707-41b9-8930-cb64fbbcbc61',
                '5d735a81-74b5-4b90-825a-cebb78a3aa21',
                '7d1bad90-2f4b-4861-8b1e-3b7956a83fdd',
                '3e3e0307-4ea7-46c2-9f82-c05426705733',
                'a8be5f1d-286c-4b7a-b4b0-fd64c6468e26',
                '62ba6e25-fd52-49bb-ac96-3991eca7b635',
                'bf5fd000-e6af-4eef-b99c-4af4291d214b',
                'ccb99766-a1fd-405e-b2b9-1d4f05358cd1',
                '41f5cad6-113c-4982-8194-4cd94c7b4f26',
                '925c018f-72de-4baf-bff5-5ac4b8dd479d',
                '4e688447-5795-41fb-9469-11c937cce91b',
                '32972fc3-8b73-469b-a994-dc886cb15b49',
                '545f20ec-311a-4661-83ea-96b18a661d45',
                'a91e737e-5269-43a2-a82b-f7546d0cf41e',
                '5a64b3cb-47c1-4228-90c8-5209ed6425a2',
                '3ad4c21f-a082-4dbc-bd0a-b30a568d7969',
                'f4460a2a-76d0-4572-afe1-91ee9de427f2',
                '92b56ee6-4e98-40e9-9801-c1e3ecc228ba',
                '4f01ac6d-2d98-4884-8c3b-307d6e8c2ad6',
                '5ecc8311-b68f-4a77-9ad4-7ef9de95012a',
                '18ec99f7-f332-48c4-a675-08721b61416f',
                '2f6c4584-61c2-4d08-b8d6-dffc454980dc',
                '2db99f31-4b2c-4b90-a6d1-005afca3f2ab',
                'acdefd8d-c5dc-46c9-a237-c9d00a9391cc',
                'd1a05418-8647-4523-87d4-fce4de19f7a9',
                'ed80e3f6-8d6e-489b-b590-eb6766c9b586',
                'bf6bfc30-41a1-4da2-a5e3-034aff21fd55',
                '679c91bf-45db-469a-8883-ccb95584ad33',
                '73c7d14c-908f-413f-893f-a0a4e4543fb1',
                '3b56b7e1-cfa0-46cb-904f-3dc03c81a8bd',
                '8c068763-e2b6-4df5-843b-54f3efd72207',
                '8bd7dc75-c778-45dd-90b5-b2c4e7990e59',
                '64fd41a9-4e25-4b23-a806-8dfaeb982bc9',
                'a9831ee2-f3f1-4b8e-a1da-4fcf388bd2a0',
                'b19eccc8-1f66-48f9-9704-510d81790b00',
                'a30d0c24-ca63-4cc3-8b84-0b829a3f1cc2',
                '31efce1f-c9ea-4694-ba3f-949b7c1f13e6',
                'eb5b3d28-a97a-4629-84fd-99811394edc3',
                '41a9ceb9-b05f-4933-a977-9085674407e9',
                'fdb09d5d-69b3-42c0-8cf8-2cdd78908205',
                'b02460f0-98da-495d-88f5-6bebedc3d934',
                '8c257f6f-ff6f-4b18-be87-f6b3a37a60b5',
                '3f1d7025-0485-4874-9b5d-f184218fb53e',
                '7a033d12-f1c0-4bf5-be54-1b82944d2728',
                '51a80cda-ab99-48c2-91f2-2cba265a3076',
                '29a9bd8f-a20f-4de3-b337-66b940d28e43',
                '08376117-c6c1-4923-8eca-497946a43f9f',
                '806a3c5d-72d0-4da4-8721-faf9b010eed5',
                '621298aa-0952-4062-ab5c-f5c67be28b48',
                'f60efb0e-9934-42a1-b942-1a0d529fd6a4',
                'fcddeb08-55bf-48ef-9d7e-4eac93529a37',
                'fca9eae2-713a-48fc-b3fb-d5a54f631825',
                'c320a0e6-ee72-417d-b3f7-a1399600d3a0',
                '83ea4548-370f-4e5b-a3ea-d2176d3f302f',
                'de5c8b48-f91e-4cc2-8379-670ad6d1527f',
                '3064863c-8a6b-45a3-bad8-0f4d93df9959',
                'a1ce40ba-1b05-4178-b1b1-b9f9d7b613d5',
                '9d5c2e99-5308-4919-9860-80c538bba843',
                '0fc952d2-5048-4e6d-b7c0-43a14d3d1417',
                'bb4300ad-e927-4fc1-8043-467a7fe2eb2a',
                'b1a3259c-13b9-489a-b918-c169b90804f2',
                '4a821e7a-b703-423d-85cb-20a29f82021b',
                '4a748b6c-2c60-4def-8f1c-3b4c57eb594d',
                'a2d9f7b4-1476-4704-a274-f445eab79611',
                '67d55b9d-e350-42fd-ac86-f784d81cc741',
                'dd608342-269e-4243-ac42-7acf08e652c2',
                '2e711537-3c9b-40f5-bce0-a5f4ecf2eb54',
                'ca82066a-16fe-46ff-8d19-9fb9e68b8c83',
                '9f42fb19-609b-4dc5-a2ae-f97e9f4fb21b',
                '456776d7-1bc6-43e3-83a4-4dc09da14780',
                'b646531a-7609-4936-a8e2-848fff82b090',
                '890709d5-8d3e-460f-9ea1-ddb248fb13c4',
                'fa31cb9c-1635-47d6-8656-2e6bf1ff7ee7',
                '47f141c3-e2ab-434e-bc2c-80e595097f60',
                'd7504108-584d-41cc-95b9-444aa46c4f4a',
                'db3bd568-ee25-49e6-b648-c9a28b4b109e',
                'c07e0469-c772-4f01-b646-0d1f8e713f4b',
                '9cd087d4-2bda-48d0-b401-76179c0be276',
                'c85b4704-bca3-4c47-b87e-c5c4db02b7b9',
                'e8f6afda-b30e-42c1-83bb-ae9085812f5f',
                '0ff8c95b-94f6-4eb5-a482-4d70438ec32b',
                '1717f26f-2cef-42b6-b24a-0fc50b0b8b32',
                '388dd2eb-2718-49ad-af2a-678e42be2ed7',
                'fc0976aa-e98e-46bd-b155-273609c1d54f',
                '29a4200e-15b6-4db6-9e8a-4d62b789b584',
                '63d950d6-926a-4183-b2a2-72160a992777',
                '48ba048c-8d79-4073-82d2-149afd016efb',
                '1d2c5c3a-458c-4a80-bb7a-91892fbe7d40',
                'bf69830d-3b94-4cd1-9735-2cdcdbeb049a',
                '1d93c23d-6a48-4933-a8a5-09d31ac05b21',
                '34c52544-ffd3-482e-acdd-d7e23f551132',
                '6b12c2dd-8196-429e-8223-a7fa749da168',
                '27d3b37b-01ce-46fe-9aae-d2110b968d07',
                'f14221b3-9e2e-414a-8914-1d313c981550',
                'd4d66f75-3d61-4e5e-a680-bd5cbd08b8e1',
                'b0d499c9-408d-47d4-8802-7a2564c3817b',
                '7e16c5b5-71aa-4d7c-9249-d6ea59c53598',
                'b17c405d-576c-48f6-abed-3e191bf5cbb9',
                'a2f95904-60a2-47e5-adf4-23ffbc2322ee',
                '05d4daa9-93c1-4f16-9a83-80acabb4819e',
                '483df363-1ee1-4d6e-96a5-661241823724',
                'acaf3b03-13c6-4676-a9e2-131cc8347073',
                '6fb48b70-c9e1-4de2-8be3-85b060b10621',
                '324e9a93-7855-443f-bf84-213f32c4491c',
                'bb3b23ab-64ea-4170-9d2d-905ce45c3aee',
                'c1e6fd53-1672-4bff-8923-386c84cdd147',
                '13e761bf-0787-480a-9d67-1d86516ec728',
                'b2d11b01-0259-4d8e-9c9d-fed2721aa527',
                '1292700d-1fee-4d61-bbe2-bccd79bdce97',
                '287f01e0-ab5f-43b6-aa4f-7b00e8dd3c20',
                'b9421938-9790-4d28-adaf-62d16ecdb81d',
                '17a3a7d5-4399-4fb5-b74a-53c63256c8a5',
                '22babd1e-17ea-4782-8d3c-b2101d8c3589',
                'c9ebb343-7eb6-4dbc-8f1d-cbfa8f32c9db',
                '1bfe4658-5fd7-40bc-a81c-a872011e5080',
                '49cad5f7-3f39-4330-b729-8fbb94c2e05e',
                '7a1503da-e561-4eb1-9a9a-9ee557b3009f',
                'a3f9b793-c4e6-42b5-8d9a-911cb1091171',
                'a500361e-5117-4842-baa5-94110de83836',
                '76d34e01-63e8-4a5d-8bdc-a559e6242397',
                '9e7a7e66-7626-4fbd-834e-f0e4fdfed4d4',
                '9cf92021-56a6-4cfa-a676-1e8acd7de28e',
                '9bf98321-2c53-4387-96d0-f8533da45e36',
                'c6e149e1-a481-47d7-ba1f-db96f9401db5',
                'd401548f-6be6-4459-8e87-19926fb3fc24',
                'b8604416-e0e8-4664-ad6b-d7f430f79c95',
                'a981cf25-7f86-4af7-ad27-e401c11b5af0',
                '12a1e58d-d722-46a4-a6b3-651bb64df22a',
                '92f5daa7-136e-48d5-b4af-ed31fcb42fdc',
                'd097f1d2-d2a7-4ea5-bf0f-94555f3203ed',
                '2c5519cc-e77e-4b00-a3b9-c007ac03e358',
                '90b8964b-c8c2-4ea0-b7f0-f7e481b75098',
                '95acfe84-c20a-4b18-a1d9-4af6ecd6eff5',
                '89212582-b74d-4400-9325-e17fe223488c',
                '731fc845-ee52-46f9-9592-bd7892d10a2b',
                'c94ec4f4-a399-40ac-984e-f120968b0799',
                '3a52d4f9-fd80-4921-9026-123f68a5c9a0',
                '6d586fbc-b1e0-4464-a595-31946f47798d',
                '84eecf12-0798-4230-b328-78700375cee5',
                'dc89e230-3ccb-4c2d-b2b6-642871ab5c35',
                '1812a61b-cc80-4919-a3a0-a51d1f30be8a',
                '694be9b3-3860-4977-9acf-3a72d189828e',
                '0068ca95-2b65-48fb-9c0d-e8b8c7b2287c',
                '2378c6f3-a344-490f-bd6d-9de26d75bcef',
                'daba8857-25cc-42a5-b33f-ab88d73751dd',
                '73d20568-1e6e-43a3-b739-8451adfe06e0',
                '0feb5df2-7062-4f23-b61b-bb5c046a62e1',
                'ad30271a-8f06-4c16-b36b-2656b27f6f0f',
                '21b4cb90-d8ca-404f-a689-8b1c70e5f26d',
                'addd3770-b818-4611-8c28-861b680c2e90',
                '21bc522f-4337-48dc-828f-259115bc551c',
                '148dd939-c1a9-4ce3-8227-510eac72831d',
                'b8e47044-298f-4a26-a668-e838222a3666',
                '3e3c847f-ab4d-4d2e-a8dc-b8c60bbe8452',
                '8cb7cf0f-d67f-4196-a776-0d963d96be9b',
                '6733d3e3-10b0-4354-8829-abe13b3a84e9',
                'f9580d2a-a097-442e-bb04-d04fdbe3d765',
                '0e350899-5453-48f9-949f-f74fe7777b69',
                'd17b801d-29f4-43ff-92e9-5e1255fd0821',
                '54919a96-649e-482f-8bf6-3350f25cdfea',
                'a7b702c4-b9d6-451c-8e0a-e7feabf7b2ab',
                'dbfd134b-61e4-4650-b32b-0ce23b7cd8e4',
                'c3821962-07f5-4782-965f-90c8bd1d3db5',
                'd7fabd2a-6804-4b56-a088-f62f9fc6032b',
                'b442b9e9-d3f6-4b9d-b937-c91466bcc198',
                '53a8e3e5-38f5-4f63-bf79-802b0a1ccbaa',
                '3825f93d-1be2-40de-8309-3c178a8495fc',
                'acd9d5a7-fcdf-4b1c-838f-776ee273846b',
                '1633af7f-6b94-4cd5-a4a0-593963fb4e90',
                'ad34e099-f808-4083-916b-3389a78c0544',
                '7c23643f-1829-4916-8a9f-f5efdfe7dc08',
                'f4ccadcc-f3dd-46b6-b835-ba076e9e9b74',
                'b35599e8-c327-4b45-bfd1-3f23a2f1205f',
                '7be8e293-1b98-4915-b0e0-386476694a1b',
                'ee9e3b14-8127-49d2-88ec-37fc232deb56',
                '91df9c1c-fa26-46b0-b2aa-3251ec6de742',
                '204bf76e-a107-405c-92b3-3268d686f404',
                '567431d0-01b2-4f75-9f17-062b27b00806',
                '6237cedd-8188-44d0-94c2-cac41aa69476',
                '1f1fb83c-da0d-4c7e-84a0-6f24ac12e4da',
                '24a42635-0a6c-4838-b524-25d79626a9e2',
                'fc86b3e3-afae-4c62-8b5b-6cb675a5317f',
                '22d509ec-b1b0-445d-a292-71a5ebfa57fb',
                '9ba0c371-f428-4069-b4d0-e18398cf87e6',
                '03451774-bb72-4449-a52c-e7ac8aeb67bc',
                '65168fcb-dea7-468a-8b40-bbe008c91358',
                '85aef5d2-0e1a-465d-aace-797a8afde916',
                '24c3d251-6a56-4ba3-8687-77bfc2cd5ecb',
                'bfcf7bc7-ea31-4f78-b6cf-2cac1c3b7df5',
                'e07bc05a-03d5-4f78-b424-a34013de4874',
                'fb24d195-e050-4575-ae7f-9f91ac3b19c4',
                '4a655755-7a92-40c5-9602-5e5680f45d1a',
                'de5ce527-ed96-4c75-a604-7d3da176bf42',
                '5dedef2d-96ff-4e65-8145-448ba77addcc',
                '1ce66f62-6761-4dc1-9df6-08082033c90a',
                '39b2dbd8-0814-4b84-8c04-d1329940aba7',
                '4ff4f018-acc8-41bd-8bf7-6b119956f122',
                '2837f4d1-59b3-4f41-88dd-f7d7a30b2778',
                '9100ade5-8b90-4e8b-b3d6-c0039027c712',
                'ba1cfc22-efff-4ad2-a032-b8a9c9cea3e2',
                '79a2dd81-0b37-4d4f-b2dd-5cc7fc5cdc88',
                'e70a53ef-b229-4d75-873d-7b4314a28ac4',
                'd04a0c05-d8e1-4977-9a5c-b6f4dce359ff',
                '3906031b-d44c-4f7e-a6e6-064dcf8b2630',
                '512d3d38-0a71-4ec4-9246-6cca3826b24f',
                '11d3c46b-b5ed-4feb-8389-3ec0f4801286',
                '8ad67d7a-0869-4037-bc70-0745d3005c46',
                'fbcf8c98-deb8-447b-83ef-c279309339d3',
                '789040cc-5c50-4415-8101-d2a7f1c32e87',
                'db76cba1-9c5a-4782-b501-12a0fcfad158',
                'c0715058-742b-43f5-bec6-ee53a11e6bed',
                '69953738-5cc4-4d40-969e-644a31e5fbeb',
                'dd466d71-9095-4b2d-8f70-a829d92a6628',
                '7d42980e-12c9-410d-9acc-36151ace3ad5',
                '427da6d1-4de5-46dd-b088-0acaeac282b3',
                'fb13eb95-8854-4005-8eb5-d9e83c033c61',
                '083a54fd-2f20-450e-8655-637a1758f498',
                '5a188c2e-dffd-4f09-9a0a-ec38ad79d3f2',
                '4d105f94-9745-42bb-b309-8d53a3604c2b',
                '458dcd6a-f972-4528-a9c7-49c027af3239',
                '9ac3da8e-5ed8-43cd-bb0b-d987fc127266',
                'd900d103-2f5b-47a5-b9bd-b1f3a7fb2a58',
                'e8c43a3f-0f85-4502-87e7-5e15477b27a2',
                '751ae60e-6cb4-44a1-8573-2a5c3be8ac41',
                'b4e2999b-7806-4f2b-804b-568d50d4b395',
                '8e431e04-2852-4437-b97f-fbb148b35520',
                '51bf8e7e-8acf-400a-a3e9-4a121a477244',
                '9b588f7f-835c-46d8-a058-cc7434a5b65f',
                '811e7f71-87a7-463c-ad6c-23c85604b40c',
                'e5d874f1-8a88-4442-80a2-12eaff38a7e6',
                'cb50bbf8-5903-4e0d-b490-1ff5ae912e20',
                '980e2e56-8c64-4c7e-80ad-c74afb7d8bbe',
                'b4bfadd3-6198-4272-8001-f67c9e161106',
                '96fe7624-7196-45a9-8f53-5918633f0b52',
                'b7ec782a-fbfb-43fa-862b-57c78c427307',
                '933cce6e-ae2d-4bcb-bc73-fda6be1501c4',
                'fe125732-af92-410c-9d88-9e69a88e335f',
                '5555e967-644a-4d7f-80da-983d6a95b5a0',
                '3040beac-f063-4ea6-8179-482c1f55a6ae',
                'ad50bb0a-d211-4dd5-ba24-4a97c23013d5',
                '559188da-5688-412c-b5f0-190f32d50583',
                '8549eaaf-1b60-4265-9802-db19919dee95',
                'd54c3136-aff1-42c6-8efa-2831cd936b64',
                'dffd5b91-a6c1-40e9-9d93-3dc282b1679d',
                'ea9ba10e-2305-4d49-9e12-4a270e433967',
                'e20f2ad0-10a0-4660-850e-af4a2499fb7e',
                'c4e00785-24b1-46a3-9c37-595bb46b6922',
                '7042294b-8f85-4a2e-9aa8-40da70b0a697',
                '5e16a7f1-2418-484e-a74f-34a549e36349',
                '014152f3-6bd9-4eb7-bbaa-96839c1b9103',
                '27485fd6-781f-4342-ab1c-3d28999f693b',
                '7456a901-482c-4af3-92a7-16d577c92c3f',
                'ab572a52-50d1-432a-8540-a2a881c57563',
                'd0df0e4f-8722-4fb0-82b4-97d10a4354a7',
                'd0c714a1-53d3-49e1-aefd-8f7292d7a9f9',
                '3a116a0e-3598-4ceb-b6ba-ddf86ecb780c',
                '705effdc-f541-455e-977d-567328afbb45',
                '8b27c907-42f2-4b6a-b939-44581f3169f2',
                '7d16d0be-f734-4506-9bbe-ddb4e469fef3',
                '7416158a-e89a-457c-84d2-e3b1851796bf',
                '08612d06-046c-4bd3-8d82-7597d64fe425',
                'ffcb8d3f-127d-4e66-9dda-680b8ae5bb91',
                '788c1a1d-47f4-450f-8408-8cda29fb0570',
                '018d140a-4f01-46ac-a3e8-539f0a60bb63',
                '425b889c-3ce5-4b7f-8c72-83b2fc3f9dab',
                '63fca750-1c98-4654-87a3-e896bfaff3cf',
                '834ace7e-0dab-46f4-a10e-c317544ff4bf',
                '474309f1-abfa-4bb6-88ae-6e7b00d384a3',
                '18a7ce44-3641-4d34-bf90-4de0d7d7c5f4',
                '12d48fc4-b492-411a-b7bf-2e33d36ceeab',
                '01bd6997-a609-47f4-9bc9-c6734c2c1e5c',
                'b09f0909-79b4-490a-b726-7f4f014e7ad1',
                '85d65ccb-6b1a-4fc9-ae3f-8ae4363bac6a',
                'b9af32c1-4be7-4d22-8629-54021c3094cc',
                'b2860425-f636-4831-a8bc-24814ff09559',
                '6f03d2d1-64e7-4ef8-938d-2dad20ba3428',
                'd8a137a8-ad8b-41d7-9904-ffa6fa30d8c1',
                '58904681-71c4-4f5a-a921-bf63be572887',
                'da900264-8bee-4465-881f-d88f31e34cc9',
                'd2fb1dba-9ad8-4247-8c27-bf488e6ea48a',
                '9415d298-a335-4f92-a19e-453d75efeb48',
                'f40d6922-b9e4-4a4a-a047-e50b3fbcd9b4',
                '33fa4d32-fb52-4a68-b5aa-b2b5029795c2',
                '1ff50c6f-cc3c-4908-8ba2-0f9748dec07b',
                'efb0b6e0-a2b6-4e65-9616-54600ef8a06e',
                '03faf0f4-9a9c-40a4-8875-b4af83bcebe2',
                '370e601d-5034-4e45-801f-6ab43c51671a',
                '0a88539a-5e4d-41f3-906d-c65a2a9c8fb4',
                '80c829bc-f723-49c8-8cea-cad411f1fdf2',
                '09a977e6-c93e-4cd9-95bc-f72137d431b0',
                '6073a4cc-769f-44a9-bb4f-58c777e9c38a',
                'f2911f2e-af1b-4602-9636-b45fa258d25a',
                '6adeca95-c29d-4035-828c-d36533397571',
                'd04e1fa3-cfd0-4011-b1f7-ab714cc4de35',
                '88c920f6-7fad-47e1-b128-beeba2d30b01',
                'f58e7710-3259-46e1-8524-cc43915ebf4a',
                '43945e6b-4ae3-4f0f-a6e2-1d6dd0d0aa06',
                '6b3eb584-7a78-453f-83b2-798720257ca7',
                '528f7dc4-a24e-4b6b-83d6-26b3aa597d28',
                'd4797af0-b6a4-4d6a-a0c2-63cbe5e7707c',
                '418c378c-c444-4ce7-a4b9-6756ebc58e95',
                '2936cc20-4c85-4235-b3eb-f4d946b573f9',
                '6600d41f-f600-4247-9d79-2c06cf2eb53b',
                'e669b484-d03d-4290-b00a-f8f3e4343b46',
                '3990a41f-3de1-463e-9114-0d621d79ff55',
                'd4789393-0349-4439-8df6-58968718e804',
                'eee5c835-be0f-4fdc-9c9c-d1a168c16f99',
                'f86f05e7-781c-4e2b-84ab-b4d9a077ea67',
                'e6e02f54-58f1-459a-90f8-f54782ea41eb',
                '7c6a5ef0-7230-4066-8daa-8e4cfd296907',
                '409f8019-a23f-46c5-819f-ed002c2fc208',
                '1cb775fb-031d-4edb-a598-e9fa90ca328b',
                '1504cce1-a5a9-4abe-826f-2b3c97ce22a8',
                '86ea86ff-e71e-448a-9f8e-6d7b245f5332',
                '629ffbbd-ffbf-48cc-b28a-22d76ca52a62',
                '7ca6f915-ff75-497a-baa7-e0e149886372',
                '5b949b6b-1e11-4367-ac91-6f726a20c892',
                'e56932f0-9185-4789-9d06-6d590ceb7e06',
                '07db8ed4-9a8f-4e74-afdf-6e1ae74ee206',
                'd15be899-d57b-439e-a214-5229869499c7',
                '143c4983-2bed-4ed6-98c5-fea51f35e2a5',
                'd29da56f-a467-4cb6-aa45-8b0ae79043a4',
                'e9ea6585-faa8-4799-862c-639a2f3bebde',
                '8ee233a5-f3ee-48cf-ae58-ed7a507bca3d',
                '64494b70-0656-49d0-955a-d56d50e77ac3',
                'd30bfba3-c491-426c-aa34-908e09378ad9',
                '04011324-f142-48d0-8709-b64a0b943da1',
                '81bf09ef-dd60-4d29-8ba6-95572baac808',
                '97c8bf4a-14cb-4698-85a3-f4d368368e8e',
                '0ef1d609-effd-41fa-957d-9b60bdf22137',
                '475c0576-e2df-4041-bfe8-6d4789c9c26e',
                '4cd97594-adec-40e0-a72e-3efed8aa4d6c',
                'ba09c9ba-7511-485e-a9f3-ff2538540917',
                '93d9f145-1ea3-4e54-9238-2bc04fef9edd',
                'ef060eb5-5f77-4abb-858a-5f2574dfc333',
                '56416336-80d2-49e0-8264-4d0e66eefdc8',
                'e986beae-7025-4f06-8c89-948223e78ccb',
                '93fc2a6f-30f4-4ae2-80d1-9e43bf652965',
                'f66a9961-65eb-48b8-b61e-bade580ec750',
                'e07795b4-1873-463f-9a3a-db670df21dd3',
                'e93f6868-489c-49eb-81bd-fc519271fac4',
                'f5278410-7c0c-4a04-95b2-a10adf665d26',
                '8f6698de-ac39-4a1d-abb3-fa90683c34c2',
                '8501947d-c4f5-4192-8318-0186bd2d01fc',
                'd97de489-7a22-472a-a95f-5fbe0e4d25fc',
                'dd08e195-e29e-4897-8ef3-5b661d2a0616',
                'fc41532d-fe36-4351-8802-e95154080854',
                '301ce7f4-207d-4769-a497-09c673697f80',
                'f1cc4178-db47-43b7-a7fb-31df2edde104',
                '374885f4-9327-4754-b46f-eaa653b377af',
                '41737796-f69c-4f35-a469-2fea096d33c9',
                '7b3ebf81-9d92-468d-8080-b8e91af437bd',
                'eb94eb8b-ce9a-4df5-966a-f2e80e667b63',
                'cb6e4cbe-4e56-4a9f-afeb-f7f103483441',
                '2a45f56f-8457-4c05-9994-7550a5ce142b',
                '8240652a-32b4-4115-a843-995d2ac8caf8',
                'ad3e587c-366f-4a65-98b2-cfa20c3c698a',
                'fe7b9fa8-f458-4eee-bcc2-e53d3bd8424b',
                '22fbb115-aac1-402c-be6a-ee0bb38354f1',
                'ef540391-4706-4d60-a3ca-20b6ad067a11',
                'fd3f8a77-cb33-45c3-a1fd-e3578537b083',
                '737bca9a-bd38-4f00-b094-019d8855cf55',
                '5135c007-be58-4a02-bdff-0e678975cc33',
                '779b9a1a-42a5-435d-8728-354b07681786',
                '9c2ad4df-91d7-4dba-aef2-f90a63e8c018',
                'bb2011b9-a8dd-4aae-aaaa-aca1a791c5b7',
                '19655d47-d8ec-4ffc-af13-3ad57ac92f5a',
                '3bff1925-8a5f-4a9b-acaf-69cd25f77124',
                'd66dc79d-cae9-4bd7-8605-d8e60429ad78',
                'bfb0e1ec-7dab-4405-9f11-3297be87d611',
                'ed5a482e-33dd-4e6a-8320-bf222443690b',
                '7f6a056f-210e-43d9-8bcd-78dbf57884ef',
                'e664a09d-c272-4f02-b451-872dfb072f2a',
                'ee5f05f6-09f1-40b9-8dbe-bb0624cc6c36',
                '11f120b5-1518-48ee-9bc8-f8b9b310c0ea',
                '777554aa-42ed-4a9a-a2c0-19ae1b1f19ec',
                '8b1629c2-6c8d-48c6-9650-5fc5a4048694',
                '31d18157-fb72-43e8-80b5-6af5b8445684',
                '2f55d3da-8683-486d-a2ae-7a99a141fb89',
                'd2d21354-9da9-4a21-9b3d-48936ba6e36c',
                '2cf43349-3535-414f-bf88-042212e9072a',
                '6b06f79f-4920-48a8-bd9f-6b859caac8f6',
                '25d2e653-056c-41a4-9ed7-5e416ab1372d',
                '3f848e2c-6c26-4d2c-9851-c6956cac7095',
                '4e8cc988-9bfd-4be9-a4a1-f15c0256fd62',
                'f0498517-c20f-4aa1-8db6-ad706f81bcce',
                '1394df7a-f1eb-4409-840b-14cc5f1a49d1',
                '9b4fa488-9c88-4d23-bd4e-b887714b095d',
                'ad619771-8ccd-455d-a937-e56eebc0d47f',
                '8599b9ec-4b5f-4946-83f6-7d63494a44dc',
                'ba6476ef-b47f-4b13-a885-cd9e0f68d28c',
                '598c8415-79a4-4177-b467-ad1141e1c8fb',
                '3adf79f4-dc92-4a85-9941-b54b923df38c',
                '75404855-81f3-4f6d-ae7f-c19bfca1ed0e',
                'd3a298d0-2580-4715-b827-781121f7cd5f',
                '1b62cb50-0af5-4416-8c64-c8a771bd5c27',
                '3128c778-9cba-4a62-b64c-96bcf28cdd07',
                'ae00c64f-3e2e-4755-af46-f76b8871800e',
                '2e2c9079-e5d7-4d68-8954-a6f05efa3465',
                '12c8518e-17e7-4f6a-aab2-85fb7825713e',
                '46fb5af1-531b-4d77-aa87-dcd694f831b4',
                '35cce617-e8cd-40d8-8d60-22034389dbab',
                '15cffd25-7241-4d91-8618-41af80f1f432',
                '0c874ba4-6754-43df-91b7-ed44e1db587e',
                'b4a05bd8-530d-49ab-aa4b-2803ba2885c9',
                '58d34528-98bd-4408-bd62-74c21f897de7',
                'a504dec3-3f72-4001-a11d-cd79289158d6',
                '51443510-ce0c-433f-8104-aaefff5caf30',
                '2dc7065e-2afe-4835-83d7-24cab5d0809c',
                'eaf1fa3b-1025-436d-a4a4-3e474f526d68',
                '48472061-ff07-46db-a3fd-f28024633f63',
                'fab0e2b4-7c7e-4fbe-b4ee-fd5325720f53',
                'cc84dc15-8978-449a-8d1e-ce301b5ea9a1',
                'dd288077-b45e-4d44-9cf5-09203c1f4afe',
                'a041aa2e-fca7-43dc-bb38-9c191810cd03',
                'bb777bad-63d5-4885-8d4d-67dc7473555f',
                '0e336100-cb79-4f0b-99a9-9b81161b5bfa',
                '506d7d9d-2e9b-4552-b7ff-b1deb135d6ca',
                '6d57ba56-8f47-4432-a16e-752eae431933',
                'f25bf53c-597f-4eca-a9ef-9d3a3a10f0cb',
                '2e06b934-3c06-4428-89ce-5ece50f8b4f5',
                '936976df-d774-4cfa-a8d8-49624c12b464',
                '2a7bed20-97ed-4de5-bd84-98918624dc65',
                'c21d5a90-a3ea-488f-9a9f-d55b00a10974',
                'd3db3bd4-c5b5-468d-a1c6-67d4d3c228fb',
                '099deb85-eb47-46e6-8eba-7c63253f6a27',
                'd462e629-52e2-4475-8963-1b7639e2e2ac',
                '77f57b96-fc5d-4d1e-b89e-6cd8b5a80267',
                '15beecbe-9160-433a-a8e2-f0054533caea',
                '091c6561-a050-4ffc-85d6-730d23abdcf4',
                'e497e131-07ac-4c7c-9392-c2069c289306',
                'c0ef2fff-67b7-4867-acd6-b1ab4b9702e7',
                'b9732198-dafc-4e27-932c-dcf085998be4',
                '106ca180-fb1e-416a-87f7-105b80e7847c',
                '4aa33172-232a-4939-8b86-5ad38941125f',
                'bed44627-bbc1-4d41-9bd7-9524aba433b8',
                'a14e5eaf-240f-4910-a05c-839b23e3d24e',
                '02c51ce3-e192-44ad-b8f0-e662842476b2',
                '286d7769-bf6e-4cea-b8dc-333491a176d3',
                'cd2d5f8c-65a5-4ae9-b814-a141c85da44c',
                'cc9cda07-6ca1-4b25-8c9d-560eba69aeb0',
                '66e0292d-ab45-4e9c-b322-205459c28dfa',
                'b56dd523-9940-41cc-8e79-3f1e994bec4c',
                '9b2b84e8-214d-49af-9399-02fc63a16c39',
                '3e14a88b-b23a-4f46-be19-c7f051c41d90',
                'da8b867e-5c8a-4fa0-bf92-1b3a41b21349',
                '65a02a75-88fa-4960-8605-e63f68450406',
                'f1fd810b-eae8-4481-8638-bfb1700273e9',
                'fdcbb2e1-0b6a-460f-906b-1f1ff9f595a5',
                '53ebe34f-3aa8-409b-96e8-28f99849cf25',
                'ba3a8df6-e087-4aac-b898-6dd8acad0f03',
                '7e1c7b27-4e90-4ff3-b7ef-2cfcdad34683',
                'f70572c3-797c-4db8-a163-fe150006eb8c',
                'bc346f81-b455-4291-bf0b-b0fd4b5166c0',
                '3205fcf5-a581-4d71-a932-4d31f3ac2cba',
                'b3e6e51c-e4b0-4b29-bd08-a460bbf31308',
                '77cc4019-727c-4559-9fc1-88b5d08f7546',
                '84243afa-da0a-411c-a6d8-526f7740213e',
                '8cd5feea-927d-4718-8b95-6cca8fcffe24',
                'dcea2875-7f55-4794-b170-80dd6ad4165e',
                '182b09b5-af03-4878-9041-dc2c850fc603',
                'e9063873-bb94-498f-9f04-4bf39dbb51f9',
                'e1c8a6a0-36a8-4c57-9fbb-46665b4cede4',
                'bc0d4779-5191-4a5d-a995-0849f2d2e61c',
                'b0cc6b5d-acd0-436c-af40-8cfcd5a14def',
                '8e98f318-aaa7-40b2-bbd2-ade6418d7c6e',
                'e41c1fad-2fd8-4645-955d-61aff309e43f',
                'ebebe219-d801-40dd-9a5b-cb0cf25c2010',
                '827abe91-0c4a-4c5e-b73a-2ba7acd62cd4',
                '5e9cad36-a446-4bf5-86a9-c11feca112f5',
                '05f56d03-1057-4027-a326-dfd426af29df',
                '04e9c2e6-27cf-4f08-bdc1-7564df8eef39',
                '429ab7e2-3920-42da-a667-1d41af6975a6',
                'ba8f3268-6a1c-4d93-b0ef-64308d43533e',
                'ee7f14e2-21d9-4dc0-a6eb-4a0411e9c674',
                '54aae992-5f3c-446c-a683-4d370d1c9b26',
                'a3577c9b-1218-4713-b12a-111a4a69fc92',
                '24fb92e0-13d3-48e5-b432-cf2e64a7d4fe',
                '4380edb9-09ab-468c-8ef3-e7d4263c4e8c',
                '607ed11a-dc98-4399-b229-bba14cb01d2c',
                '567cb2d3-91bb-44a2-af3a-a1a755f87751',
                '1af09c5a-fda8-4e52-9dbe-0ee324ccfcdd',
                'ae74b9a9-595c-4b23-b386-c24ff33b43de',
                '5da11a1b-2426-4d18-9650-a4e85142b8a7',
                'fe7ecbea-e4b1-43f4-ac04-77e073a7b5fc',
                '0424c843-d794-446b-89fa-8a8990cc827b',
                '262374c2-2704-4b29-bec5-02a5730b6df9',
                '31f40bd6-20ff-4298-89e5-3caf10e54a10',
                'd44d2199-1401-4c12-baf9-01cc8815fc12',
                '03c77f0d-f8cb-42f6-9021-2aae06bfb4ed',
                'cf028bfb-ead6-450d-8ae4-aa45a96eff93',
                'e91709a5-e036-479d-b0cf-e4fb9286e6e9',
                '7b5d6303-75bc-4b33-9fd2-a0fdfae6dcc0',
                'f05dd4c4-8119-4a95-8561-b788fced71d0',
                '58d8e97b-3829-4484-ac74-ea072949634f',
                'a6a66a82-8985-4f73-abb4-f74c6baa2363',
                'f05193ea-d6f5-47dd-98b5-934a75be5d91',
                'cf492559-24a7-4029-ae65-8c03a996254e',
                '78f17c9a-1bbb-45b3-bcb9-742c0966b5c4',
                'd56695f0-3a87-4112-b67e-941dcc3401c0',
                'a0f3fa9e-3386-4b5b-ad36-f15d4e837856',
                '0c254d87-6776-4e75-a701-bc0d629f1785',
                '84bc3b48-fdba-4c0a-8e98-b25c24176cbd',
                '1e6180ca-9c95-4f41-bb01-6cb50e5ce75f',
                'ba17b072-e371-4568-918f-69be4ff498a6',
                '798e9723-776f-458d-bc7d-99d29164a932',
                'b553260e-59a6-4116-83fd-0ce4df86b9a5',
                '229d7c3b-3be3-45ae-951e-be4f65d6ac56',
                '2fe209e1-fc92-4ec9-bf6e-613d38fb72e8',
                '2d318aee-6228-4e6c-83e9-4b606d146ca3',
                '2dde6be4-e70a-4847-825c-cabb5816f7dd',
                '05ee3ceb-f83b-4e38-9a91-d5fa54f9bcbe',
                '0c1b5bea-506d-46fe-a8fd-81a424a16010',
                '46395d36-89b2-4e9c-b0eb-f0cd2ac3d7e4',
                '3dd8e302-33cf-441d-9968-4171d99e7744',
                '35fdbd5a-6f62-4d9d-9b9f-079688740a7b',
                '6004e6bc-e930-487c-ae69-8a6df887ea03',
                '7d4e9684-a34e-4f52-8e63-b58c4d8793dc',
                '86a3d483-d67d-4a19-8781-0d19abcbb94e',
                'f564bc82-968e-41df-989e-e8ba9fd83977',
                'c8630a51-712d-4db5-812a-0b9ef6694efa',
                '2ceb7199-1c15-41d7-96bd-fb0007fd7b34',
                '1b476cd8-2ad2-41d9-857e-8701a1acdf39',
                '0edeea62-d236-47e1-8258-ae606f0eca81',
                '05208a3b-bba8-425f-9419-81559b13b7c1',
                '75f50ea0-5f2a-42b4-a3c3-088a0b958a65',
                '50eab18b-0c7e-426e-88bc-23f2bd720238',
                '65150e26-a11d-44b6-971d-89823fa93064',
                'c0a48315-dabe-4ca4-a457-11b915a3e154',
                '5b8e7bd3-87b2-406e-a131-0c7a1fd742f4',
                '2dc45249-7176-451b-b212-e93e6c0022d0',
                '9114e563-c96a-4fe9-9b7e-91a44c6bb487',
                '908e7f77-2e8f-4bbc-bbfc-170df340d648',
                '9d79c43d-8ab2-46d8-b79b-68251aac3871',
                'af94549c-77ba-4f8e-903f-8b943d750ba3',
                'de345473-bbbe-42d6-9cd0-b9c1d92b88c0',
                '81d8930e-0fe6-4ca9-a86f-57be3be29e36',
                '861df492-b77a-403a-be2d-5195793442ce',
                '4967d518-8135-451e-992d-fa453178d80f',
                '6cf2cc5a-d976-41fe-b9e7-a8717f31bd5f',
                '42cd7b7e-5f72-4d0a-8a12-f6e7ba2adf8f',
                'a866755b-3286-40f7-abe4-d71064d830bf',
                '3ef83871-eef1-4c9c-a681-0bf75cce2bc8',
                'b364779d-4803-49a4-938e-056944ae9a10',
                '35b73ed8-11b4-41cd-9e37-b71cf94ce72a',
                '5589375e-983d-416c-bceb-eb2698074016',
                '3580f35d-36b1-4a9a-8519-b573ceefdacf',
                '19ffc5d4-d52b-4036-9a4a-2d8d5e893867',
                '4d404556-ea68-41eb-8bea-a96a48760763',
                '0976e894-1f9d-4628-b718-962aeda1a6ba',
                'af2f6eed-148d-4588-92a6-28097f602d28',
                '55fff15c-8414-4d18-8338-2c0c01581695',
                '6051a00a-ae1f-4b3e-af84-5cbd40e751fe',
                '3f671435-add3-4e52-abe9-46e363b21a46',
                '6d0243ee-6b09-49ca-85aa-05bda1e39b74',
                '2d9c98d8-80f8-46ca-9cd5-3f355aa1ff9b',
                '849f08f6-141c-4cba-8cb1-8cd15d0382a6',
                '470925b5-c96d-4d0f-96e4-49d479a61ecc',
                '76087e74-2c6c-4139-af47-0703c8994e40',
                '07f9edc7-7ca1-4773-8bac-88f54275e04b',
                '318de880-dea2-4a29-beec-6072225bba4b',
                'b7fa8b1e-25b9-4e1c-a451-c251aa9da98b',
                '01720611-b11b-4975-916a-78066f43ccb3',
                '608d7462-31d0-43eb-8862-5a4b3d6aa908',
                '6e2b2196-71cb-4a0d-be54-086894831c91',
                '6c931389-3d4a-4610-9b24-06242e4dc78f',
                'a3c8a66c-b143-4632-93c3-6e2b68b31348',
                'e6f75615-f837-4c86-8f44-9c4578eff206',
                '7be450a9-1552-497d-918d-7f92c5a1abee',
                '1173cf52-997a-49ac-9e53-27377181c3c2',
                '51b57951-8b89-4b7f-803c-9728bedc52fa',
                '517a0b2d-1522-46c5-8a2c-b0d1f67218b5',
                '8495e899-5388-4b1e-9779-4104d7704011',
                'cfa83d59-8262-493a-9077-ab9f550e6301',
                '8f5d5640-38e6-4b34-bcdf-62817fd32ba1',
                'dede3046-71cd-4ea7-b3a2-ab791754de38',
                'd4b67021-5324-4543-b584-a2b416770364',
                'd827b101-f44d-4d1b-983e-072fbabe50bc',
                'fb2e375d-4af5-46b9-a758-fd0d5f66a59a',
                '93fdae7c-76db-440f-9be8-9850ecb05544',
                '09dc910c-7d5f-4e15-9d62-f40bf2ec151f',
                '7617f78a-9e8b-4f37-a31f-f13e0220ba39',
                '0db8fc7f-4742-454d-b02f-d659281420d9',
                'e85bd66c-0797-485c-93ca-16d5cf7e6a8a',
                'df7bc7f2-d35f-4d23-9d0a-678c70f38d58',
                'd03005be-44ed-4d60-a8fb-3eda8d9eb26c',
                'b22a5d21-3c0a-4114-a699-da29cc43078d',
                '9e797105-43f1-4a2c-9f54-6b9ffc3448c8',
                '0b2be45d-664b-4956-b8a3-4d3933f9e6c3',
                '0640e532-07da-4a1a-886f-a6dba8e61985',
                'aa3b6e1a-de6d-406c-9616-b48f2a131e56',
                '97d571c2-7a65-4008-b584-2531cf16b412',
                '5c1e6463-44f4-485e-9dd1-bc6995119713',
                'df32497f-366d-4aef-8ac2-91443cf723c3',
                'be8ec26b-4ed8-4096-999d-b9c864bdd516',
                '7015c0d3-b26d-4111-8104-03ef25c691ea',
                '276efdc3-3278-4881-843b-5221fef43bf2',
                'cb3dfef1-6aa4-44a6-b7ff-9b4bcc6b7dd2',
                'fe57d8e6-b762-46ea-9f8a-2385d6776607',
                '42320228-7e71-4717-ab86-8d419bf916b1',
                'e4941cd8-1978-4178-800a-1acccc2eeacf',
                'b78f9e04-def9-4495-a88e-949d8d9f2865',
                'dcab1e5f-78ce-48ff-b1c9-3ce96a216d3b',
                'd1ca9171-3291-4729-8089-4e3e76ece34c',
                'f9c2a753-4f39-4247-b8f3-82c7cfffdbbf',
                '5ee03648-1b1c-4443-a99c-07ec3409a224',
                'f12683d7-ae02-41f0-b654-20f1380a0fa6',
                '40dd305e-b6b8-4326-bb10-9e0e4423583e',
                'c672f308-04e2-4755-9ab4-3b81083f874f',
                'a84ba8fc-b39f-4243-bb38-c8acd8ded1c9',
                '0d66059f-21da-4428-a16c-abf8485f92b3',
                'f4eef8e7-4f83-470c-b52d-b5333bc9fad2',
                '6a9a7dbe-e09a-4376-9706-924952c4dab7',
                'f3c46350-6f3b-4583-a5fd-b6d4dfe31dc4',
                '1db6476c-d643-4160-b208-390ab1577f12',
                '9d2ff81e-f247-40f6-949c-b3c9edcfbc4c',
                'cf2f2d6e-422b-4666-9b43-ff231be58ab3',
                '21f9c94a-1c65-4e5d-8dc1-8de37de15c05',
                '6e3c46da-7e37-4cbd-b923-d0339ea2c8fb',
                'e0544da6-4371-40cf-a738-6f33514b3219',
                'dc8da2a1-685a-404e-81cd-c468d093a4e0',
                'd1472f5e-023e-48d6-8c28-d56201dc1d57',
                '0698a087-647a-4e86-9115-50871edb8797',
                '6c679eac-2a8e-49b2-864b-b8efc55b0975',
                'c7a689c7-1e16-46c9-9167-40a006eb830a',
                '2bcd6368-2d63-4505-ba48-72e4b4931297',
                '5bf9e9fc-38c0-4225-b7d8-e5573503a8e4',
                '33cc7954-2c0f-4a8e-8937-38dd5cf402c3',
                'f5af5da4-438a-4a7a-9253-b1f3f7d1def2',
                'c9a17d62-4d4e-4f97-acf0-5645c3510d6c',
                'c8af8c5d-b65f-4880-a16b-b02735885d82',
                '5b791870-4fba-4e7a-adc7-9ff3359e06fb',
                '3a6bbfe4-94ca-466b-a26b-888605137594',
                '909e2206-078c-4a0a-b12c-918893289dc1',
                '55d06d6c-bc8c-453e-b89a-749107ff22f5',
                '05718de4-69d7-42b4-97e3-6907df2705da',
                'da52433c-222a-4ea8-b2bc-1a28b8c745e8',
                '6b603982-7904-493e-972f-0a178c45ee74',
                'ef0cae2a-0a61-414e-98fc-6af60e71016a',
                '6e908334-7e78-4b92-a17f-9199300eccee',
                '066d601d-4d6b-471e-bee3-a6b377155e00',
                '6abddd39-ea70-4fe8-8a7d-80a00552c6be',
                '6fa9d3a9-cec9-4ba2-9ebe-407b4a1ca484',
                '296570d6-aa12-49c1-82c5-ec9409cc216c',
                '5aac7361-926e-4717-958c-00aaeee5765f',
                '1fafe582-7ce9-49d3-9e0f-20604b0b05f3',
                '8476a88b-c7e2-47f6-999d-cb6c6d8f582a',
                '43aa48a2-349a-4a95-9f78-861a0a9f2814',
                '136c7ae5-1175-433a-bc98-7c55be61d2b5',
                '964cbb0b-2447-4ba2-9155-113924d4b754',
                'f58a82d8-d7ce-4fa6-8c38-2afde4d01d6d',
                'a8ee99d5-cd0c-433e-9d01-7c199af62fb6',
                '951e983f-7e4d-4329-95ec-a0cb21069ff7',
                'a24537dd-6c9f-4a75-a30a-d8ba70383a8d',
                'e8a8e491-1915-4314-b531-d54d5816eb1c',
                '1945ee39-1509-4db0-b09e-9939805832f4',
                '675d75c3-0db9-4a19-af89-c0a6e91d4cbe',
                'e2292a3c-e77f-4690-8b89-fde3a9916869',
                'e9ec63b6-39a8-49c0-95ef-2d5ca2496552',
                '6666e4e5-e570-4117-8f7b-8f0ed5ca9dac',
                '9a08edb9-96f2-4277-89b6-b1cc7fbc2bcf',
                'd398dbaf-ed36-4705-8fee-5f03c257ea84',
                'b29aa5e7-e37e-48dd-8d9e-c718e24b6e44',
                'f5047783-dc1a-410a-b943-bf215c62191e',
                '7d269f76-d42e-4adb-bf0e-87eaf53073da',
                '8ea0e594-428d-4de2-9a1e-d9edb0d2027e',
                '435cf742-07bd-4d4f-a917-cdc87840038a',
                'e87eb64e-1962-45c1-adc9-e1cc7475c492',
                'cbd53905-89a7-4480-b66c-5d74bc23470a',
                'd5ffcab8-a527-45f1-a0d3-7422fd4364a6',
                'fa99bbf5-3bdf-4ba2-af85-7c9737e0c550',
                '4f835c45-fa63-4e71-aaf9-b80cadcaa8a6',
                '532969a7-75ba-4a67-a240-9a9e5864071a',
                'd4239466-3244-442a-8823-62f3819318ed',
                '98b7be33-abd0-43bf-a86c-903d450d235a',
                '879ad913-be0b-42c4-9bdb-c51854a306dc',
                'f7f72583-1057-4096-9cce-f0d627e65dd3',
                'b11f626e-1763-42eb-97b1-591e5398c43a',
                '19898193-8d5c-423c-82c5-dd2790f6c13d',
                '0569a960-de9b-49b9-a3ab-fb765ec04f55',
                '8e9eb739-459c-463c-947d-af95cd4a4681',
                '660cc08c-58c4-4ef3-8abb-df70e36750fb',
                '5eea28ba-099a-426c-99cc-c168a16c7f89',
                '2d6ad3f5-9b56-4513-b904-a45f965193d4',
                'e1c94a3b-dcb6-4774-9317-cf4ffb4cf8df',
                '31bf748a-1379-412e-837b-c1decfb3a68b',
                'c1bc0366-27f5-4dcd-99f5-150c76c72b7e',
                'e70249a9-b1f4-4e03-92ed-d18b732ed676',
                'd50e682d-feaa-42ea-9f83-dce5e4693925',
                '64fb02f6-8138-45f3-8d99-b304745850ca',
                '5f2d18a6-0ff2-4f7e-9972-0dcddd74e334',
                'c9c60f15-f260-44d1-bb44-092eba71998b',
                '94c9ef7e-c2c9-4c04-b3be-bfedbd1e375c',
                'e2b8d5aa-1c9a-4ff7-bad6-1ed67042b12b',
                '20306ae0-b97a-4797-8b5a-b0a3006624f0',
                '2888230f-638f-4e3c-baea-2634283e34d3',
                '8182b664-cd57-4383-8303-60e726a7d916',
                '3a71a8ce-6769-411c-9bec-876d5a6d3821',
                'dcf719d7-54c9-4b54-98d3-aa632edc6e07',
                'a3a39087-2cc4-4e29-b091-ea49e4e720e4',
                'f6547d31-3021-4860-a3dc-d777c68111bc',
                '57768fd0-5cc1-46bb-b3e1-98219ef9970d',
                '2c548c2a-0f83-4898-9eb0-cae6d4676ddd',
                '0be1df73-d14f-450d-b70d-60aed3f4c1ce',
                'bae318a2-e628-42d8-9ba3-ae78b93600c2',
                '849c79bc-edf3-41e0-9e28-3585dee30d48',
                '52c0f406-ee9d-41fe-bfca-f1f3128d11ba',
                'e77afacb-e8c4-4389-8bdf-3a3b342526a7',
                '1097c14b-b296-41a3-ac65-47bf340b5d6f',
                '2f947fe1-32c3-4c5d-94ad-8acc23bede46',
                'a95c5f6e-036e-4566-8f7d-e897647e5863',
                '6b70ea4d-ecec-4956-b9fc-6d0326b74d8b',
                '971486de-f7d9-4aeb-94da-e1f1922b10f4',
                '64f47507-301a-4161-b969-111a0a840898',
                '8fa7311d-a161-4ea2-a82f-36f726a37f10',
                '5dd29ec5-dc53-4ea0-95c9-3c5e32d0d6c7',
                'b22e8055-94eb-4048-a35c-87cb11d8ca92',
                'b40fc21e-0a94-4b7c-b273-99dd6fbe8d02',
                'd555e0fd-13b5-49a6-b703-acd429557278',
                '0b2df55d-b013-42a0-91fa-339cec7ae7b9',
                '893e24cd-1a20-4dcd-b13d-2f9d043d264e',
                '672a7e1e-0756-4008-88fd-69ff9befa3c4',
                '9204dd7e-cded-4475-9dcd-b3082d9eabb4',
                '5b70754c-b1c8-43d6-966d-91df1ccb1a40',
                'a88b4e39-d116-4429-acea-ec2905a5adc1',
                '1da0cf7f-7ec6-4b9e-98ee-4ebb40223813',
                '2767c3b3-6e77-4d25-8cf7-e68c59ea6cf9',
                '75dd969d-3b89-45db-a558-cb2cb0c60795',
                'b7fd5418-bc0d-4196-be76-1088513a5b92',
                '835c44b3-e64e-4112-b2d1-92782fa94644',
                '8eeee518-7e81-4ca4-91fd-f4e35c27ae53',
                '7d1f94da-12e4-4459-ba4f-e74f9df9c530',
                'fb85269f-b29b-41ac-a196-f78f6728416c',
                'dd798a9d-e9c0-4d21-96cc-27ea88f6787e',
                'f0b3c2c4-6b73-44b2-aa4f-b21616925bac',
                'fe4f889c-f245-4009-ae36-bd7673be374f',
                'c315c0be-7db7-49fb-a331-81dab02f267e',
                '2b4dcce7-46f5-47de-9c68-5544a02b38d2',
                'c3e7ec66-3188-4f53-88bd-c88b39be47aa',
                'c8a66909-fc68-4525-be77-72ee6f9c4c99',
                'f6fc41be-c258-4523-856a-327385192692',
                '563885d9-d4eb-4568-8bab-9c2c8ec2e785',
                'c332d45d-1f8d-4990-8c9c-a525ce02c836',
                'b13807e7-052f-48c4-a56f-396676a65712',
                '073ab5d2-13f5-4595-8191-e555cc7e2228',
                '3b7ef707-4873-45b1-b20e-f3ad4e388f7e',
                '3c273089-903d-4d8d-81ad-4a4aba79b168',
                '74bbe7a5-965e-44cd-8993-9bc40c64aa6d',
                'e90a91ca-0f04-4839-b655-4056bf683e0e',
                '7ad53eee-311e-456d-a595-ba127987d759',
                '4cef9331-9248-4ea8-a62c-aa115f57e009',
                '453edcab-0693-4e96-bf7c-98ad097832f2',
                '0f864421-f04b-4463-bb2c-c222bec01c6a',
                '67d1437d-54e8-48bb-b293-eb1e5997c3a2',
                'a0ccc4d1-62cc-49e7-bbb8-f3f1d389cf01',
                'c895dc28-cd4c-422d-927a-8855fb3a299b',
                '04f42df9-ee9d-4e1a-959c-25727a29abf4',
                'ab8fb41e-ec51-4f02-aea4-5513d49b7098',
                'd8d39566-5079-4755-a14c-044986f51f74',
                '8f3caac2-9aca-4d20-8716-db9de137d1f1',
                'd4d35817-7739-4af7-9215-4a2c47f03885',
                'bed62a32-1a06-4201-8626-dd8ae4ce5c38',
                '581878c3-794d-4de2-af21-6857af060245',
                '7fd9a3c2-bfcd-4fab-98e4-cc3504655a11',
                'b5a911fc-7624-4627-adc0-8f0ab4fd6fe6',
                '20a5b945-e8a7-436a-b066-309789849108',
                '5b5b330a-b7fd-4e60-9ab6-985a601659c0',
                'e3a46f73-5b7f-42fe-92e9-c1085e8690d2',
                'f79319a5-c247-4db6-a487-fc358fe4c284',
                '1ee6bf55-e854-4530-a352-8aaae37a8098',
                'd5e0cdd4-0ac5-4cbe-929a-032e812099d8',
                '104fc412-f162-43b2-956e-cf59b16223c0',
                'dacdf746-7933-4b59-a5c8-3c5294fd1215',
                'ff69f945-94dd-4a3b-9c3d-2bd16756d063',
                'df6428b0-5a3c-4de2-8a04-e04defa6d077',
                '4defd5da-f88f-419b-aece-3987ea62d44b',
                '6b0fdb57-d83a-429d-acd6-cfabdd40d5a0',
                'bcf6e412-4684-4024-9a69-998c7e82bae1',
                '537880e6-3158-4e43-8d02-39c4d51be5c0',
                'a9cd92b6-b608-4c39-a80d-cbaa3c488cd0',
                'c74a6b6d-39de-4807-a78e-faa82cd620fe',
                '75fa2015-e951-46a8-96c4-aaf1dba48b05',
                'fe730f08-ad80-4361-9edc-6412fba98be7',
                'b87f9e97-3bc8-44d4-9f90-35eec9cdb9b2',
                '0624f9a3-42c7-4849-ab64-ca01b3c0abdd',
                '2e4c885c-dfbe-48d5-b6fe-527628197d0c',
                'dec914a7-f36a-4a40-8f17-8ff48ee4d40f',
                'c99abea2-7dc6-4a04-920a-d6ea9366da8d',
                'c52a183e-381d-466a-bd67-83c79279be40',
                'ede4f8a3-71fa-4d65-b7e8-ad237ee826eb',
                'fddcea4d-5314-48f7-a6c2-a12e6274998c',
                '902c82ea-6f31-4e22-b883-ba89a8e3edbb',
                '4106ac33-13a4-4c91-9d2f-d22fc95c2a08',
                '74a676df-09df-4b3a-a100-c29b2b552c29',
                '36f14e52-851c-44f2-9062-b8cd4c003a8b',
                '4fd83a64-dc02-49cc-b028-6b00f91953ac',
                '336fbacd-04fc-4efb-95fa-df7097c3b426',
                'ac5ea504-ffeb-420b-9cef-8a0a40430c46',
                '2bca235f-918f-4003-a5c8-fc7f4bacc788',
                '26e09e80-ebad-4c9a-91ab-7c5b126949e8',
                'f67ae539-fd50-44be-ab32-39d9a23c4b3e',
                'd88f9a70-af17-404b-b37a-16da8bbe24e1',
                '753b0ac4-976c-4595-ba57-25f032a5db8a',
                'e0cf85bc-a115-4d20-b502-a18dd25963cb',
                'e30ba317-7cbe-4941-9ad1-1e9c1f8a29bc',
                '54f0204a-092d-4fe6-85f4-e141ae531a8a',
                '9991b539-be6c-44b5-9944-16ca083ef099',
                'c85a812c-72c4-47e9-9013-840115c2d82f',
                '11173220-0b1f-4976-bd56-c6a08fe2f2af',
                '16f6cc47-1d5d-470c-835b-d9a9b1149d5c',
                'b52a4759-7946-4658-a5aa-f817e92afd8e',
                '61fc4279-ed87-4a11-8826-648668f6a940',
                '28614c56-3417-4503-b948-bef007a7b8bb',
                '4d4a0f7f-7ff3-4f6d-88e1-39e539c6b5ea',
                'c4b74eb3-f3e9-4e86-9843-45292567d319',
                '3a4bb9f3-33ab-4d0d-842b-e4ea4ea0de85',
                '7b55df91-108f-4069-9bbc-98990ebe2f97',
                'aab7a45a-f623-4cbc-b21a-540d70fe1533',
                'b5b1c3a1-ca7a-459b-8e23-fbfc26e8551e',
                'f1e6d31a-ede9-4592-8f5c-b2e7c3d94d74',
                'db8a9a00-912c-4352-90e7-797e59b18e7c',
                '1541d01c-aa29-40f9-b6d6-c4cca4ce7a63',
                'ff4aa30f-ff2c-4c6c-87a9-7b926b6eab29',
                '3a311966-6624-4a60-a224-96d466de1166',
                '63fd3921-9067-4337-8fda-a73926840148',
                '12106ad8-d3ca-4271-b669-a7893ec6cb6d',
                'd5e989c5-7fb0-4a8e-90ba-b854f9adafca',
                '151c3bad-b8c6-4e95-955b-45904106404e',
                '656dd720-11ac-4e45-ac1c-1e0b3a414c33',
                'aeb22a94-88e2-42d9-9448-ce4805771d42',
                'cd339318-88bd-4ddf-9025-3ba90a654dc3',
                '7edb6003-dd2c-432a-a37b-941404fbc87d',
                'd91bee75-eaa0-4449-bba9-f91d264d8ec8',
                '29f98189-27ce-49cb-b39a-e8f5a89a5095',
                'e1111cfb-cb5d-481b-9218-59beff97eb28',
                '46bcfc0e-66ce-4fb5-90a5-42dbd72702cb',
                'a0bee183-dc08-4c8f-8add-34f8ad332333',
                '029067ba-b484-4273-bafa-98b19c993881',
                'c02af376-03c7-416c-842d-bbce8d3c0814',
                '9c0c9238-002a-473d-a2a1-8016381d5e08',
                'ba7c9283-2ce9-4524-be07-78b5a5543fc7',
                'cf486827-8eb3-4c0c-a462-705df97c80bf',
                '2715a97f-e95b-4f86-bbd7-8ba99d900ec0',
                'd05b8481-a46d-4a15-acbc-d4a5e802df3f',
                'c463fe88-6493-440b-8b31-6d4e7d74fd9f',
                '9b609abb-1f12-48af-93a2-e7d2da6e2282',
                '2f6b19c2-3950-403f-b9fc-1cbe64defc44',
                '18b64329-6186-4a9f-a1f7-afe125c061fa',
                '925b12e9-0304-4905-851e-555e5d4015b8',
                '19bbee86-4614-4922-93fd-16cbe5cd40af',
                '50ecf15d-e2d0-43c0-98cf-6d7800472ce9',
                'd53a458f-ae60-422f-8e9e-b6556f173cb7',
                'b2c618f5-0074-4e75-b805-7278001636e6',
                'cf0868de-6847-4024-af85-c1dbb2914fea',
                '41d809ea-6ad8-45ce-8452-a6d383b9180e',
                '73821519-f438-4308-b1dc-0ecbca3d285f',
                'c24563c6-a0f9-473a-943f-651727e41e93',
                '984b613b-3873-4d32-841f-4ca6fc9e8985',
                'd4af2859-bafe-411d-9995-e26fd6e00b77',
                '3402b23b-a00f-4b90-8c5a-ee9e8f3a7b76',
                'eb52c786-2d5b-45d1-ba84-bd9dde5df928',
                '74215725-ceba-4b9d-8934-04456c7d4b3c',
                '0f4a3ee2-d141-460a-bfde-c7719b6c6105',
                '8170a0ca-659f-418f-9b29-4eee934e6311',
                'dc131e7b-fcf0-41e2-b77a-50a28258897a',
                '438e50ca-9ab1-4f27-afa4-edc36f68a7b5',
                '95705469-5b3e-48b9-847c-74a688ff260f',
                '00047979-7323-46ba-9716-fdc9f3791c0b',
                '586fa54f-c4d1-404a-931d-d33bf8379929',
                '88918899-7253-427d-9861-e986c0e1adb4',
                '51951b58-a275-4dda-9f42-587d49091c3c',
                '08dbc72f-d612-40c9-985c-9fc7d075442e',
                'f79dbe7f-79e1-42ca-8297-d81079716589',
                '4079496a-c2f3-40a3-a130-3f1cca1f9f01',
                'bc1e7f93-7760-4bcd-8c64-322377378439',
                'ad5d2c21-a6f1-4f71-bf2b-20b1957e354e',
                '9532150d-2a5f-4c58-a969-69093b892248',
                '761a71a1-a632-4a07-afe5-e565dcd0eaae',
                '497d6db1-4865-402a-a553-1a4b097a46f3',
                'de969e90-ed5c-4eca-be11-c3d08771a7fe',
                'f6e892a0-a0a6-469f-ae2e-0406eeac3063',
                'b666babd-2933-4a18-99ea-110ddfae9009',
                '2d56f7b2-e296-4824-adbb-7854f7ba3d95',
                '95046601-4fdd-45d6-9745-980121af76d2',
                'e01fdc3c-9f51-4c07-ae35-a31611afe551',
                '5af14808-f42b-4a12-aca3-961579f1574d',
                'ec10c366-d299-4358-b353-4e05a18635a2',
                '9d07234e-73e6-4af1-9e8b-fabcbb1e0bce',
                '3cd3d27e-cb45-423d-b477-0bd30e0ceaec',
                '82e574ea-5b35-496f-a74b-9566628a97bb',
                'ecfd980d-8fda-4fb9-9957-15e2f36493ba',
                'bca96982-7f7a-420a-bd8b-adb08dd760d1',
                '86ae32df-694b-4411-808f-39130d7d71a5',
                '694feefe-0573-45f6-b46e-8b6616bdb684',
                '59206df0-5a58-47ce-8768-d9994a92969c',
                'f699c32f-0235-4ac6-b030-0bc362ba9191',
                'a57b8980-3edd-41b0-8e78-7b9f32c2d5df',
                '9cd9e086-210d-4dc9-8002-ca51979d3fc7',
                'a79b0db8-8099-48b8-aea6-23f6f42ded7b',
                '2ddfd8d3-7808-4eec-81b0-abcb912cde04',
                'c7f244b8-eef0-4200-b4d6-0f35bee8fdf5',
                '71147132-6629-486e-b5fd-4c9ba5114058',
                '091ddd56-eb2e-4779-ae9b-d2800dd8104e',
                'fb5d8ebd-0142-4a4e-ae6f-110653dcef56',
                '4a2d552a-715f-45d1-94c4-7f8ea941d56d',
                '322588e2-4233-44a4-815d-40159d5c2172',
                '082650a4-b0cd-483d-9e76-5f8c5a4be252',
                '9fd8fd15-9c54-4a3c-88b9-8b267ead6d91',
                'd39969b4-d510-44d0-b2d7-67bdb3ac7b34',
                '177fed14-2e3a-447b-9da1-0440f61eda8b',
                'bf72c511-8b7f-4d6a-b1f1-19b55381d466',
                '992eee80-3b34-445b-994e-26e1b618d183',
                '2fb6faae-7061-4b01-9911-3d2f126f44d5',
                'b62c970f-d36e-4d3b-9f13-f5a2ba832193',
                '0e8d4841-c79e-418a-83ab-d4bf90d01c63',
                'a41923bc-4a8b-42c5-ac65-f3cb621505eb',
                '270789b5-0c58-4153-a280-cc8b0eceb0ea',
                '3ddb0ba7-9ce9-4728-bc5e-c0dea59360ed',
                '0ffdcd09-07ef-4049-96d0-57be03856688',
                'df96bc5d-172b-4138-a536-35fedaaf5f95',
                'a8d59ba5-bfbe-4359-b9a8-d297d265fc28',
                '49f7cf61-5f83-4bf1-8c0d-912a078a30e6',
                'fb661b6b-7866-46b6-8253-457671647b7a',
                '05ca0732-4086-4d23-b52e-a49b210b6a58',
                '1f1e1e66-2401-452e-bdbf-7464afa85e76',
                '7451d991-d0fe-48cf-b444-e4336e09eb39',
                '3ed38e0b-b7ee-4009-92d9-7e9adf005599',
                '5a392a12-3c6d-406b-89a9-035beb8327f7',
                '665f6c8a-a39d-448b-ba41-923daf633d41',
                '77dda11d-6f8a-443a-b1d2-f22ebcf312b7',
                'b6ab87a5-b8e3-4a07-bbe1-e5f7e5eab4fc',
                '139595cd-5abf-4359-a2c0-90a085f1f26c',
                'b9818f1f-bae7-4dae-93bf-9776e1daeb3d',
                'c5772a99-41d0-4417-9e89-234cb4d0cfc9',
                '420a513c-55ca-43ac-91a3-0077d0f6c1d9',
                '3ba8ad76-721b-4c9e-85b1-24c00c3c4734',
                '1cda218d-23a2-40d9-833d-faec235c3cba',
                '033bde4e-d5f8-4b70-aa30-6afea30dcbd3',
                '212b29b9-7600-491b-af21-d958b9242eed',
                'ceb9db92-8ac9-4e13-a81f-8acaaa95ac72',
                'd6d67ec5-aa3e-4a9e-bcb5-617b8ef96e1e',
                'd5ff5261-0dcf-4738-86bd-31efa27f844b',
                'caa4ad5d-24cd-4d67-a170-27a9b39d56e2',
                '69d050de-ca35-4b13-9d21-fa0d73ae6296',
                'f1c10f03-fee2-40c1-84c5-424adf348d9d',
                'ba5ff763-90d5-4cab-a7fa-154872cf7d73',
                '7a47bd03-b8fe-4c29-bb2e-d7c383c2cc7a',
                '7f1e62aa-c8a7-4d04-986a-fe9beb650c92',
                'a4738fe1-8d58-49a7-a6cc-1103fa654ace',
                '624521e5-5dff-4aa8-b33c-f1b37458c1bb',
                'f79ebeee-7c7c-444d-9b55-7b1ea6be894d',
                '37d97a29-0753-4cc3-aac2-08ab6ab5c86d',
                '7cc83a52-774e-405c-8e35-883f18cafbeb',
                '964fe61f-e576-419d-b32d-4961bf705370',
                '2507fb8b-331c-41a9-81e7-5ada02472222',
                '7204235a-4be4-47dd-98fe-1dcc94937103',
                'eb1a3f98-0556-4912-a33b-d3b0bb6ddb88',
                'd1bfb9a7-6eba-486e-9105-bc08d772c6e4',
                'e69ab6ab-3987-4137-b89e-a3d2ac0bc3c4',
                '70d41c4d-280f-43e2-9801-20a756d18593',
                'c1e1551c-e364-4409-bc81-0763d92e780b',
                'dbd02da3-0a0f-4bb0-a499-cda9da18b6e0',
                '95ddabda-e08c-4357-92ac-4535b731136e',
                '57146817-816c-4056-8d60-43e96ad0ab22',
                '5239c56d-3f37-45b9-8c01-45504232375f',
                '066c44f7-827d-4468-96dc-abbb8735cce4',
                '5ff717e6-4aec-46ba-8c8e-d5685f044499',
                'da35711f-0ca3-46ad-8e62-bcc99fe83e76',
                'f8a9d589-905a-4f36-98e3-ecf75e831cad',
                '281049b6-df59-4da8-ae68-ef20d7aa4a85',
                'bc6fb59f-c91c-4c1d-9363-0b6f98fbb03e',
                '524b899e-b142-426b-ac8e-175ae04eb7af',
                '66bc1647-8207-4399-b136-7889ba832efa',
                'f2997b84-3e6b-494f-850b-e7a638efe79e',
                '392c90d0-aa54-4856-8da0-5e33649064d1',
                'cb8b5a19-e914-4b24-a37d-1825c0c0ad97',
                '3f2fb720-857f-4199-a355-396ea5430ee9',
                '72f5be58-61b5-4fba-93b8-08b45b43b89c',
                '1925034e-3c92-4d27-a802-5a90067b1562',
                'bb39e388-6a55-4e93-9387-d162d209a3ee',
                '96b8a3d3-a9b4-4c99-9408-b8261fa5ce50',
                '68c3776c-37d9-47c1-a774-77641d40a589',
                'af5ff9cb-e49c-4e7a-9959-8eb74da5a2b0',
                'cd01180b-277a-44f5-abc9-c64ea067a4dc',
                'a4b4ac1d-8426-4ad7-b225-b56456a40a0e',
                '407bd510-d12e-482d-af76-5fd907ea26c9',
                '725179c5-aa22-408e-b079-31c23d02f4e2',
                '243c21a3-82c4-4e5b-bfe9-d7778971fdec',
                'c421eaed-c8fe-4f2e-8ff4-e913e84b2b43',
                '26b1b73a-45bd-4b22-98e5-cd357982753b',
                '25bd9b96-8881-4be9-a4e4-8019597d4c56',
                'e35fddae-215d-4454-89a6-59cc0766623d',
                'd4b3323e-2dd5-484f-a8de-dcdaed329371',
                'bfc40837-5cf2-47a8-b0ef-ad3710e18d44',
                '0643c519-1714-46fd-bd33-216a4aeb807c',
                '8ef6a4e1-d9c4-4ed9-8cd1-5299e58e9f20',
                '75fc1f65-43d5-4dea-a9f9-a9e9b0386d3d',
                '9b865c93-4dba-4719-a395-7077f7fda06a',
                '1e14ecbb-bbf5-4d7e-8172-a17692163a5d',
                'b4d89c27-55a9-4d69-a5c4-e74e3e975ddd',
                'c6a06196-4d86-4429-bf87-9db3780f25b5',
                '2323a2be-8d5e-472a-9074-7f2a6a574cce',
                'c7ce825b-002e-487d-a936-6aef74e8d3da',
                'b5478abe-916d-4d90-b546-90ab3793285c',
                'ebf2d553-4041-470b-b1e1-6b9a2682d1a2',
                '61666ddc-3aae-4a87-b81f-d47745b78ae6',
                '03670c77-09e8-4831-866e-435a73cc2928',
                'a4251453-cea8-4145-be9e-39fc82ee1693',
                'c5c21f55-02e3-4a22-bbf7-66fc19c892d1',
                '0b806d18-01d8-4262-9af0-89967a19da11',
                '0ec58f9d-3b36-4d25-a463-144f9c520abe',
                '277ab825-2615-4c74-9f07-232c01bab4b4',
                '884d2c47-7757-4fcf-b192-fb8536b03e9b',
                '34f57bba-05db-41a4-8eab-53d25afde2fa',
                '08d2ea03-654f-44ec-bafc-e93f4e0234cf',
                'd802656b-b11d-4305-973e-e67fcb2bc5e0',
                '70f2133b-e57f-49a8-954b-eb8039a029bd',
                '3850bd4e-6eb3-43af-8a87-3d21a7267dac',
                'f57dabea-c40d-4e8d-b857-c1ee3457ed68',
                '72d55e76-d7e6-4598-bfa1-8cb962eb2a7c',
                '0b8b1838-e2aa-49fd-b4ae-af5ea3700e20',
                '26e63c54-62c2-480d-aeb5-2e46058c4bcd',
                '1d9f579a-e864-41b8-9a82-34647612cf2b',
                '6fa6413c-fc5b-4625-8092-020131962ef0',
                '097a66ff-416f-4284-88c7-2895c1c837d7',
                '8d11a799-8555-4679-80b2-08004e588e71',
                'cc9d4169-5922-483c-a4ba-d5f73a7975c2',
                '3ed57777-b6a9-462e-a767-b5af9a15aaeb',
                'e73ba47a-163c-47d5-a60c-3ce38d106d25',
                '26a1d3a4-5f49-42e4-9ff2-5bd8b9cbd8de',
                '0d4a0008-b943-4bfc-ac83-b86ad9f74488',
                '6c88544b-8002-4e7c-bcc5-4709524081d3',
                '908d3bb7-cfe8-4984-b97f-9e41e36247ce',
                '5b5661ff-68e0-4604-97e5-41eac40e431b',
                '2a3a419b-f766-4512-8efc-78370a56eb11',
                '9b4c5428-2a38-4cf4-bfd4-11ef79ea36ac',
                '36824ec2-7019-4aa4-9734-5dc6d02062e2',
                '67a0dad1-3581-4109-9118-c5400c974cb6',
                '3095e634-3649-407e-bd36-a2061d140277',
                'fc36b531-27a3-4d82-80e9-331b66e090ca',
                'fe1c55b6-f829-47bc-a644-68c7093ef374',
                'cc3d23ae-843a-4464-af31-ad0652f83015',
                'e0553bd3-de45-4ef0-a5da-ca4051382ca6',
                '4c0ff66a-ac4d-4f29-917e-35380b819444',
                '3bae88c8-1b67-480e-82e8-67ca8c800a36',
                '6248a80e-1875-484b-9865-b53d1d62a8ac',
                '48030db3-3ebe-4022-8387-d2445b572677',
                '08fd5982-d6a4-42de-a159-50d0b54f113a',
                '65ed8e0e-81a9-4770-b1e4-5ce2c723494e',
                '23f77a02-1b59-40dd-b732-cc0382a01848',
                'e1e67c76-d5f8-4638-9610-c356f610f16c',
                '31bc187f-8524-4186-9932-37b86c061fc3',
                'f72f74b1-f754-4fef-b20a-6966f2f1c006',
                '1910c591-0a7a-41c0-86f0-036225490cf3',
                '77c7542f-d413-4c42-962a-b1d41fa42904',
                '2c187b73-efc8-4294-906d-dfa43bb91e84',
                'fc4fc36f-0465-4bae-bc2e-222d39ccd860',
                '07dbc8b7-467d-4463-afdb-3e2e3603feee',
                '9b1f670a-b0e3-4188-8911-9644f03aecb3',
                '159a9762-84d0-4a7f-87e4-b295520b3eb0',
                'dc3a729c-181d-45aa-9a2b-2f0d5951eb10',
                '60ca9701-83d3-4684-b5f5-ee8afc1eef80',
                'b7378b62-d62b-4594-953a-3a6deca41694',
                '36edaec8-8c1b-4213-85e9-d787d2217060',
                '0c566d7d-fef3-4b6c-a4e5-a62651f8bbc6',
                '0573a51b-9006-4d0e-a21f-66588edb1e88',
                '75b6da38-06ed-4cf2-9882-5cab45e00fdd',
                'b0134867-2783-489e-9201-4d11ab9917de',
                '12420253-4e6c-4e76-b284-7d2320a1770d',
                '11760f81-e714-46c0-a5b3-39f56d108ef2',
                '681afeee-e43d-4fe7-b2e3-7071f228178d',
                'a8d1b13d-7fb2-4fa8-aeee-951b48399fa3',
                'a6a77fc5-9402-4e8d-bc6d-53d0bdab7482',
                'aae6aa73-80e5-4d9e-90b5-e8834c1430a3',
                '670e05cf-177f-47fc-876f-219fc58034ea',
                '8121938c-93ad-41dc-8066-318eaf782720',
                'b534e36e-9370-4c2b-9e5a-8771909ed828',
                'dcdf79f7-18c0-47f2-9b23-fddfd1cbad51',
                'a4cfce2f-0d66-4d08-bf7e-743e3e316b07',
                '8dd21c2b-3fa2-4591-937a-afe2cbdf62e6',
                'e36e09cf-d764-49dc-9bed-39f0111c2b14',
                '87399a8f-8cba-4721-a1ea-47e9999709ee',
                'b0e1222e-d257-4d13-a636-ff765d5dc7c2',
                '9b71b948-d872-44d5-90de-8b78d647a069',
                '09522aa3-65a1-45a4-9e80-73565a0d34df',
                '2a428448-2da0-4471-b136-7172e332c205',
                'bdcba9e2-88ea-4a5c-b9a2-b4ab587837db',
                'a6a36711-4159-47f0-83c9-26d47ae78a5e',
                '5f1ff9f4-d658-40a0-9c0b-55098fbcf330',
                '630362b4-d461-480d-b745-b6f88814cd4c',
                'efe635d6-0533-4bc1-ae3d-505b81a52c72',
                '4fb8df66-8dbc-498d-9247-493e5acdc740',
                '3324999e-b35e-4c4d-a681-2e86ce7e2b43',
                '8ca9266f-15aa-4ea7-893f-7393a6fed23c',
                '172ffde3-dde8-469b-85d0-9f5f63b429fe',
                '699c750f-333f-413c-bf1c-6240e68d2fd8',
                '187f3b8c-7d0c-489d-af66-d6436812435b',
                '12f6f574-44fd-43d2-a1a6-95b506ccea83',
                'a9db1171-1768-4415-a537-19b2128e7023',
                'b1a4eed9-8fea-4287-acb5-bc46d63b1670',
                '455f542c-7dcc-47dd-baa0-4ef04b5ed05f',
                '1ef0bc91-d760-4539-91c0-dca1f85a3862',
                '9a2c58ab-a6ee-47e7-bada-75116a05b665',
                'b4cb3aaa-695d-415f-a7b4-be0339a381ef',
                'be79c62e-f0b8-482f-a9f4-1432ffeb446b',
                'bbfce08f-7e39-447a-a7dc-4d668e5d47a3',
                'ead0d1aa-1180-4da5-bf34-5355ab59cb5a',
                '9be0613e-4db5-428f-b77c-fcbb86586f70',
                'faeffcbe-715d-413e-bacb-99fd8ed4e791',
                '34034c77-a18f-48cc-9ff0-579294126770',
                'b4bcf5f6-2784-4b4d-babf-f3e640e04225',
                'a76473a4-33b9-4810-a743-9f5c42e39f2e',
                '76c5a2b7-3878-4ef4-bf92-3f1bc552aee6',
                '10da7294-ef8f-4fde-8a02-87957f49dfd7',
                '3cd82c13-c2e9-449b-8163-1464cc38064f',
                'b58272b1-2a40-45cf-874c-c4d718c8579f',
                '2bb71e45-ba59-4582-afc1-c2d29885f866',
                '44a1827a-543d-4eb7-9eaa-ea58ac78776c',
                'bff163cf-3fd3-4cf0-be40-bf2241ea43fb',
                'c751b88b-b75c-4542-8818-5c84f8e0ece6',
                '9f113da7-aeae-47ed-8700-273c6959b8f0',
                'd683da0a-0b68-4e51-ad46-a44da644a56c',
                '70833505-acb1-44a6-839f-08a1a631eae6',
                '927bf107-ec2b-4e2c-ab7f-468c5edab73f',
                '8ca5a4f2-caf3-45c8-8331-e90c915828f4',
                'c3866744-5632-4b85-8f16-f18905f04921',
                '9bd675dc-31db-4ec3-8b4b-45a2eddae815',
                '33111122-6e15-447d-b5dd-3983163d3b6b',
                '6cb2a679-a2d1-4d2e-97ad-bdce355ba4ba',
                'd841d410-1eaf-466a-80d2-af0fc9a12573',
                'e8837f3b-20aa-4101-8fd1-dcee1cebcd4e',
                '3d30d02f-6793-4c69-850d-a487b1176dd8',
                'b4b064eb-5793-4538-8183-7b297f02bdeb',
                'ba4eb2c1-c043-442e-909a-8e53dc0b7444',
                '875b01b9-bbf8-4196-839a-f74e236717dd',
                '3d631df7-90ea-4101-bc67-824b39d863c5',
                'b5b7ff0e-1617-482f-a7ef-4ec323980729',
                'a5a57c20-57e2-443a-996f-34afbe507928',
                '2f2dd160-c520-4cc0-abd2-6b2634adecec',
                'b16a1832-ad02-4979-b691-a5efdf6df2e8',
                '850f380d-1ac4-4272-8a63-82003acd5e3c',
                'af944346-9c6d-47de-8bae-97ef366b6820',
                '848d1c26-67fc-41ac-9d20-65e2f93d3cdc',
                '2c78d7c9-bada-4cab-9529-e938db1e5ea9',
                '65e4cf3b-cd65-4e7f-b82f-73f515be226f',
                'f45c6e3f-e742-44ca-961e-df72005d4bf1',
                '563cfaa6-77dc-4ab3-bc4b-de84a4044336',
                '88c4a7b9-69b8-436d-8806-f82698c18585',
                'f1ff8f21-0c8f-4d56-bc96-c349cec4d30f',
                'e0491aac-2912-4487-8a5b-99c42335eb00',
                'a4580bc5-471a-489e-8f4e-6af9163a46f3',
                '9d2b6d76-b075-4d1f-8d51-1cade249ef81',
                'ef02c1b2-f548-41af-8324-6e1c06e1e0d7',
                '11f23d37-51f0-4d09-9e63-b3f8da68d678',
                '24323895-e048-4a68-ba98-459319cfdae1',
                '18a8304c-27a1-4c39-aaf9-682245760435',
                '68b1ddf3-b159-40f7-a2e5-0aed9884150a',
                '11c810e2-e0b8-4d4d-b077-786fbb7b23a2',
                '17eda0e5-0577-4f01-9d0d-0ca06807b0c9',
                '34531583-5bb5-4bc9-ab4b-8080fad8a5fc',
                '6bdf8a17-df08-40c0-aaa5-ffa5f11da832',
                'd0fedcbb-24ca-45a9-8b7f-da3c8b007584',
                '722b1c69-58a6-461e-837a-d159b4134b98',
                'f7f49c01-1ed1-42d3-9098-ef09f1f58775',
                'cb65ca8e-8d96-4a63-9ffd-d56cb1ca90c7',
                'adf49ebc-e5d1-40d7-b9fe-93b330c4e7cc',
                '7810e5fa-2550-4f3b-927b-2a1d9cb5dd9f',
                '239dca45-b9d3-4f31-b3dc-29548e5cee6e',
                'dd576110-c792-4a7e-9eea-add23f127e81',
                'f485403c-33e5-42de-b931-76a2e7905d0f',
                'a0e9bd04-f3e7-4572-87b0-02a8c2d313a7',
                '8ce806d1-d1bf-42d9-a6d9-cb4554207ea1',
                '24799145-01a3-4882-aeac-795a71034508',
                '9f0dcff9-cbf5-4c0c-bb77-a37e343df407',
                'fbe0cb66-c089-4afb-bf90-0e301a3a4d5b',
                '198f7d5f-b6d4-4477-b554-43b51cbbf6a5',
                '9755bac5-4f9e-4958-82c9-e70f226b2083',
                '482b6f6a-a7c4-4a43-921a-8c776b60637b',
                '5c697733-5353-4657-987a-ce7cac4a7f91',
                'a8577e5c-3372-4139-8856-6bbd7ce8e0e7',
                '73743411-f5e1-4179-994e-1fccbf10c4ce',
                '22e2bf71-7907-4fc3-95c8-1005eb717b22',
                '24224ff6-5efb-464a-a684-b4c67a290ade',
                '98dea352-d271-4c9a-8c6d-d7ec794b17c7',
                '8bac8696-cf60-446f-8773-c71f445d09e8',
                '0423a118-f18c-4563-b8a3-1b88a8d3ce84',
                '094b9af9-ea9f-4071-9712-a6f4b6a997db',
                'd72925d2-2a2f-45d1-9132-37103c7f1588',
                '3d486150-886e-476c-b2c8-b8a661e7af43',
                '8a2d054e-2d4b-4c9a-b127-683343ba8c23',
                'eca76ef1-fae9-4991-a9df-740ffac64a13',
                '2ee409cf-efb1-4bf8-b402-006d4d946da4',
                '1f2f5163-a265-4de0-8306-b97afe3c588e',
                '370fc3c9-49e9-4e1b-b59a-74a735fc617d',
                '7d245810-575f-4b70-b1ce-564a63b606a5',
                'aeb1cd50-9a6d-49ad-9c8e-9dbc2abeac7c',
                '807c41dd-0836-474f-ae79-47e66c4c2844',
                'e08d98b9-3211-420c-a14e-2654b50198fa',
                'f272ea16-63be-4a99-819d-ffd677cf3bdc',
                '62f329f5-bedf-4ca7-a6fe-621a2fa95a37',
                '9992d5b7-ca7b-4c26-8c16-bde3eab5329c',
                '63b527c6-2971-4ba9-95d4-5e1f62fa2602',
                'd6d86df6-646c-4e55-a23b-5286abd2e152',
                '90ac1c4d-725a-45be-ab0d-9e02675be03d',
                '7ab5be58-d604-4da8-8b0b-8b3a0aae7337',
                '5ac5cecb-7f3a-4930-b516-2f25e5901e85',
                '868cdaf5-f853-4342-93c3-bf39d8391d1b',
                '5ba3cb29-53f6-4bf7-9dbc-7affd736a671',
                'f9639781-7ace-489c-b43d-5be7bdf753ff',
                '84f6cd6f-4866-4e2f-b96b-e076caf653b0',
                '4bbc39ee-6bf6-44d0-89aa-8c02728c0a3d',
                '39af3ecd-2137-4dfd-bda7-afd18e65724f',
                'da888dd5-de6a-440a-919e-63d5f8d519fd',
                '29d0b2bf-4c66-4e16-9e7f-f6a81c12711b',
                '21361b30-880f-425c-a510-c6aa38c30f05',
                'e60c1d5c-2b5b-42c7-aec7-ee068c9640ec',
                'f23f24fd-87a5-48b1-b17f-a5d3c00e952c',
                'bec63a84-b007-4911-8684-f5bb53f0dde2',
                '4dd48dd3-7577-45ee-b3d1-db1bc27248df',
                '41b206cd-82e0-4629-8fe3-3f6e514a2945',
                '4de55183-4bd5-4b66-becb-b7c4f5656330',
                '33979bca-e16f-49e1-9ead-f9d13c4ce622',
                '01351ca6-7517-40ff-a319-e4f0d20a2bc2',
                '71c42242-b10d-45c0-9049-0edb966a4987',
                '738d1b0e-e412-4580-961a-730b63e36a77',
                '40b296ab-d454-43ca-8c41-3e793c01b0e0',
                '2f6d12e1-625b-412d-bbb8-8228cb86ec2f',
                '0bae3f77-a39f-4e37-a745-869ed96b8ce2',
                '317e4af8-35e5-4561-8273-6b6303cae3cc',
                'f5c64e14-8df0-4c96-b25b-bda1410a5fd9',
                '20e7b1e8-9bca-4d84-86a4-3734c28ad180',
                'b909d566-6c49-4600-bd7d-4a9a48cb1d0a',
                '8f745357-10c9-4ed1-bae0-7c6dcc1e460c',
                '60d5c9a5-0f86-40d3-86a3-f90c21727b5b',
                'd847e27a-5190-4074-bab1-c387d324bfa1',
                '5650cb59-13e8-4923-9406-9a0ea366d827',
                'b66ad993-aab2-471e-843a-583aaf8523f2',
                '3e036393-8284-40f4-a095-88cd19bd649d',
                '16d8ca50-6ce2-4824-b99c-cc1b9c299c7a',
                'e9b7521c-3e1c-4e9f-b6ba-0df4ebb836f2',
                'e82ea09e-f059-4293-bf1d-6e2696e28a7b',
                '0d92a123-5bee-4200-a3fa-05ebebfa2352',
                'aa3dc4e0-1d5a-492f-8393-cd134d62376d',
                '168bc6f1-efb2-409f-9f3a-2f2d94bff346',
                '8a37c9c4-f187-4fa3-a36e-208742d9100a',
                '63e8964b-d72f-4c2a-8ad9-6a8623808242',
                'e574a550-df75-4577-b128-7844eb812f36',
                'ce0f6833-7043-4961-a82c-2307eab9275b',
                'dfa00ef0-8142-41d0-ba5b-13a3f6df1b3a',
                '7347042c-65ef-441c-b355-f7bf05649f32',
                '86209e0e-582a-48d5-9d49-c6c61a79b0da',
                '05323cd1-108d-470e-b7c7-54987fead5cb',
                'a2d2e4e8-c58d-4bbd-a10e-a090aa944aba',
                'f77b5e3e-e05f-46bd-ab41-c84023dec149',
                '36c57f01-fc1f-4288-a9ed-78f43a52069d',
                '67aa64e7-7029-4434-9af5-75ee7f6f040c',
                'c9bc9880-3068-4a60-9190-c6f8d92ec082',
                'e688f798-9e7c-446c-93b6-ffc74a814fc8',
                '8f88c23e-691a-465c-a6b8-5ebaf7600cd4',
                'a1642a95-f4a7-40e9-b956-1d838f07c8eb',
                '810e1434-8122-46ef-8351-c3f87e7797d1',
                '68371dd9-14e0-4e82-872a-dc13ac69b143',
                '3766cf3f-f131-43fc-87ca-1576b3587f36',
                '819d8e74-7849-42e2-815c-8d459a43747c',
                '9e090b81-36a0-47c1-a632-7e93a9a4e945',
                '8f046713-56e6-4da7-bc9d-130715577481',
                '17b8a7cc-7a9e-4271-ae8a-7ff0a95876a5',
                '2819f05b-b3bf-4acc-bf2b-364961ecba6e',
                '300ad1ff-3a35-4bcc-a1f7-b58e35c86136',
            ];

            $query = Building::with([
                'assignedUsers.user',
                'engineerStatus.status',
                'lawyerStatus.status'
            ])
                ->whereNotIn('globalid', $globalids)
                ->where('field_status', 'COMPLETED');


            if ($request->filled('engineer_id')) {
                $query->whereHas('engineerAssignment', function ($q) use ($request) {
                    $q->where('user_id', $request->engineer_id);
                });
            }

            if ($request->filled('lawyer_id')) {
                $query->whereHas('lawyerAssignment', function ($q) use ($request) {
                    $q->where('user_id', $request->lawyer_id);
                });
            }

            if ($request->filled('eng_status')) {
                if ($request->eng_status === 'pending') {
                    $query->whereDoesntHave('engineerStatus');
                } else {
                    $query->whereHas('engineerStatus.assessment_status', function ($q) use ($request) {
                        $q->where('name', $request->eng_status);
                    });
                }
            }

            if ($request->filled('legal_status')) {
                if ($request->legal_status === 'pending') {
                    $query->whereDoesntHave('lawyerStatus');
                } else {
                    $query->whereHas('lawyerStatus.assessment_status', function ($q) use ($request) {
                        $q->where('name', $request->legal_status);
                    });
                }
            }

            if ($request->filled('damage_status')) {
                $query->where('building_damage_status', $request->damage_status);
            }
            if ($request->filled('field_engineer')) {
                $query->where('assignedto', $request->field_engineer);
            }
            if ($request->filled('final_status')) {
                $query->whereHas('finalApproval.assessment_status', function ($q) use ($request) {
                    $q->where('name', $request->final_status);
                });
            }
            if ($request->filled('building_name')) {
                $query->where('building_name', 'like', '%' . $request->building_name . '%');
            }

            if ($request->filled('area')) {
                $query->where('neighborhood', 'like', '%' . $request->area . '%');
            }
            // From Date
            if ($request->filled('filter_from_date')) {
                $query->whereDate('creationdate', '>=', $request->filter_from_date);
            }

            // To Date
            if ($request->filled('filter_to_date')) {
                $query->whereDate('creationdate', '<=', $request->filter_to_date);
            }
            return DataTables::of($query)


                // Building Name
                ->editColumn(
                    'building_name',
                    fn($row) =>
                    '<span class="text-gray-800 fw-bold">' . $row->building_name . '</span>'
                )

                // Engineer Name
                ->addColumn('engineer', function ($row) {


                    return $row->assignedUsers
                        ->where('type', 'QC/QA Engineer')
                        ->first()?->user?->name ?? '-';
                })

                // Lawyer Name
                ->addColumn('lawyer', function ($row) {
                    return $row->assignedUsers
                        ->where('type', 'Legal Auditor')
                        ->first()?->user?->name ?? '-';
                })
                // finalApproval 
                ->addColumn('finalApproval', function ($row) {

                    $status = $row->finalApproval?->status?->label_en ?? 'Pending';

                    $color = str_contains(strtolower($status), 'rejected')
                        ? 'badge-light-danger'
                        : 'badge-light-success';

                    return '<span class="badge ' . $color . ' fw-bold px-4 py-3">' . $status . '</span>';
                })

                // Engineer Status
                ->addColumn('eng_status', function ($row) {


                    $status = $row->engineerStatus?->status?->label_en ?? 'Pending';
                    $statusName = $row->engineerStatus?->status?->name ?? 'Pending';

                    return '<span class="badge ' . $this->getStatusBadge($statusName) . ' fw-bold px-4 py-3">' . e($status) . '</span>';
                })

                // Lawyer Status
                ->addColumn('law_status', function ($row) {

                    $status = $row->lawyerStatus?->status?->label_en ?? 'Pending';
                    $statusName = $row->engineerStatus?->status?->name ?? 'Pending';

                    return '<span class="badge ' . $this->getStatusBadge($statusName) . ' fw-bold px-4 py-3">' . $status . '</span>';
                })
                ->addColumn('actions', function ($row) {
                    $assessmentUrl = url("/showAssessmentAudit/{$row->globalid}");

                    return '
<div class="d-flex justify-content-end">
    <button class="btn btn-light btn-sm"
        data-kt-menu-trigger="click"
        data-kt-menu-placement="bottom-end">
        إجراءات
    </button>

    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4"
         data-kt-menu="true">

        <div class="menu-item px-3">
            <a target="_blank" href="' . $assessmentUrl . '" class="menu-link px-3">الإستبيان</a>
        </div>

        <div class="menu-item px-3">
            <a href="javascript:void(0)" 
               class="menu-link btn-show-history"
               data-globalid="' . $row->globalid . '"
               data-building-name="' . e($row->building_name) . '">
               ملاحظات
            </a>
        </div>

    </div>
</div>
';
                })

                ->rawColumns(['building_name', 'eng_status', 'law_status', 'actions', 'finalApproval'])
                ->make(true);
        }

        $users = User::when(['Legal Auditor', 'QC/QA Engineer'], function ($query) {
            $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['Legal Auditor', 'QC/QA Engineer']);
            });
        })->get();
        $assignedTo = Building::distinct('assignedto')->select('assignedto')->get();
        $owners = Building::distinct('owner_name')->select('owner_name')->get();
        $municip = Building::distinct('municipalitie')->select('municipalitie')->get();
        $neighborhoods = Building::distinct()->pluck('neighborhood');
        $assessments = Assessment::all();
        $filterName = Filter::distinct('list_name')->pluck('list_name');
        $filters = Filter::all();
        $engineers = User::role('QC/QA Engineer')->get();
        $lawyers = User::role('Legal Auditor')->get();

        return View::make(
            'DamageAssessment.audit',
            compact('assignedTo', 'engineers', 'lawyers', 'users', 'neighborhoods', 'filterName', 'filters', 'engineers', 'owners', 'municip', 'assessments')
        );
    }

    public function auditBuilding(Request $request)
    {
        if ($request->ajax()) {


            $user = Auth::user();

            $type = $user->hasRole('QC/QA Engineer') ? 'QC/QA Engineer' : ($user->hasRole('Legal Auditor') ? 'Legal Auditor' : null);



            $statusRelation = $type === 'QC/QA Engineer' ? 'engineerStatus.status' : 'lawyerStatus.status';

            $query = Building::with([
                'assignedUsers.user',
                $statusRelation
            ])->whereHas('assignedUsers', function ($q) use ($type, $user) {
                $q->where('type', $type)
                    ->where('user_id', $user->id);
            });
            if ($request->filled('eng_status')) {
                if ($request->eng_status === 'pending') {
                    $query->whereDoesntHave('engineerStatus');
                } else {
                    $query->whereHas('engineerStatus.assessment_status', function ($q) use ($request) {
                        $q->where('name', $request->eng_status);
                    });
                }
            }

            if ($request->filled('legal_status')) {
                if ($request->legal_status === 'pending') {
                    $query->whereDoesntHave('lawyerStatus');
                } else {
                    $query->whereHas('lawyerStatus.assessment_status', function ($q) use ($request) {
                        $q->where('name', $request->legal_status);
                    });
                }
            }
            if ($request->filled('damage_status')) {
                $query->where('building_damage_status', $request->damage_status);
            }
            if ($request->filled('field_engineer')) {
                $query->where('assignedto', $request->field_engineer);
            }
            if ($request->filled('final_status')) {
                $query->whereHas('finalApproval.assessment_status', function ($q) use ($request) {
                    $q->where('name', $request->final_status);
                });
            }
            if ($request->filled('building_name')) {
                $query->where('building_name', 'like', '%' . $request->building_name . '%');
            }

            if ($request->filled('area')) {
                $query->where('neighborhood', 'like', '%' . $request->area . '%');
            }
            // From Date
            if ($request->filled('filter_from_date')) {
                $query->whereDate('creationdate', '>=', $request->filter_from_date);
            }

            // To Date
            if ($request->filled('filter_to_date')) {
                $query->whereDate('creationdate', '<=', $request->filter_to_date);
            }

            return DataTables::of($query)
                ->addIndexColumn()

                ->editColumn('building_name', function ($row) {
                    return '<span class="text-gray-800 fw-bold">' . ($row->building_name ?? '-') . '</span>';
                })

                ->addColumn('assigned_user', function ($row) use ($type) {
                    return $row->assignedUsers
                        ->where('type', $type)
                        ->first()?->user?->name ?? '-';
                })

                ->addColumn('status', function ($row) use ($type) {
                    $statusModel = $type === 'QC/QA Engineer'
                        ? $row->engineerStatus?->status
                        : $row->lawyerStatus?->status;

                    $status = $statusModel?->label_en ?? 'Pending';
                    $statusName = strtolower($statusModel?->name ?? 'pending');



                    return '<span class="badge ' . $this->getStatusBadge($statusName) . ' fw-bold px-4 py-3">' . e($status) . '</span>';
                })
                ->editColumn('actions', function ($ctr) {
                    // Using route() helpers is cleaner than url()
                    $assessmentUrl = url("/showAssessmentAudit/{$ctr->globalid}");

                    return '
                <a href="#" class="btn btn-light btn-active-light-primary btn-flex btn-center btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">إجراءات
                    <i class="ki-duotone ki-down fs-5 ms-1"></i></a>
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                    
                    <div class="menu-item px-3">
                        <a class="menu-link px-3" target="_blank" href="' . $assessmentUrl . '">الإستبيان</a>
                    </div>
                </div>';
                })

                ->rawColumns(['building_name', 'status', 'actions'])
                ->make(true);
        }
        $users = User::all();
        $engineers = Building::distinct('assignedto')->select('assignedto')->get();
        $owners = Building::distinct('owner_name')->select('owner_name')->get();
        $municip = Building::distinct('municipalitie')->select('municipalitie')->get();
        $neighborhoods = Building::distinct()->pluck('neighborhood');
        $assessments = Assessment::all();
        $filterName = Filter::distinct('list_name')->pluck('list_name');
        $filters = Filter::all();
        $assignedTo = Building::distinct('assignedto')->select('assignedto')->get();

        return View::make('DamageAssessment.auditBuilding', compact(
            'assignedTo',
            'users',
            'neighborhoods',
            'filterName',
            'filters',
            'engineers',
            'owners',
            'municip',
            'assessments'
        ));
    }
    public function housingUnitsByBuilding(Request $request)
    {
        $query = HousingUnit::query();

        if ($request->globalid) {
            $query->where('parentglobalid', $request->globalid);
        }
        $type = auth()->user()->roles->first()->name;
        $filters = Filter::whereIn('list_name', [
            'housing_unit_type',
            'unit_damage_status',
        ])->get()->groupBy('list_name');
        return DataTables::of($query->orderBy('floor_number', 'asc')
            ->orderBy('housing_unit_number', 'asc'))


            ->editColumn('housing_unit_type', function ($row) use ($filters) {
                return getFilterLabel($filters, 'housing_unit_type', $row->housing_unit_type);
            })

            ->editColumn('unit_damage_status', function ($row) use ($filters) {
                return getFilterLabel($filters, 'unit_damage_status', $row->unit_damage_status);
            })
            ->addColumn('current_status', function ($row) use ($type) {
                return optional($row->statusByType($type)?->first()?->assessment_status)->name;
            })

            ->editColumn('owner_name', function ($row) {
                // لو عندك full_name بدل owner_name
                return $row->owner_name ?? $row->full_name ?? '-';
            })

            ->editColumn('unit_direction', function ($row) {
                return $row->unit_direction ?? '-';
            })


            // finalApproval 
            ->addColumn('final_approval_status', function ($row) {

                $status = $row->finalApproval?->assessment_status?->label_en ?? 'Pending';

                $statusName = strtolower($status);

                if (str_contains($statusName, 'reject')) {
                    $color = 'badge-danger';
                } elseif (str_contains($statusName, 'accepted')) {
                    $color = 'badge-success';
                } elseif (str_contains($statusName, 'review')) {
                    $color = 'badge-warning';
                } elseif (str_contains($statusName, 'assigned')) {
                    $color = 'badge-primary';
                } else {
                    $color = 'badge-secondary';
                }

                return '<span class="badge ' . $color . ' fw-bold px-4 py-3">' . e($status) . '</span>';
            })

            // Engineer Status
            ->addColumn('engineering_audit_status', function ($row) {

                $status = $row->engineerStatus?->assessment_status?->label_en ?? 'Pending';



                $statusName = strtolower($status);

                if (str_contains($statusName, 'reject')) {
                    $color = 'badge-danger';
                } elseif (str_contains($statusName, 'accepted')) {
                    $color = 'badge-success';
                } elseif (str_contains($statusName, 'review')) {
                    $color = 'badge-warning';
                } elseif (str_contains($statusName, 'assigned')) {
                    $color = 'badge-primary';
                } else {
                    $color = 'badge-secondary';
                }

                return '<span class="badge ' . $color . ' fw-bold px-4 py-3">' . e($status) . '</span><br>';
            })

            // Lawyer Status
            ->addColumn('legal_audit_status', function ($row) {

                $status = $row->lawyerStatus?->assessment_status?->label_en ?? 'Pending';

                $statusName = strtolower($status);

                if (str_contains($statusName, 'reject')) {
                    $color = 'badge-danger';
                } elseif (str_contains($statusName, 'accepted')) {
                    $color = 'badge-success';
                } elseif (str_contains($statusName, 'review')) {
                    $color = 'badge-warning';
                } elseif (str_contains($statusName, 'assigned')) {
                    $color = 'badge-primary';
                } else {
                    $color = 'badge-secondary';
                }

                return '<span class="badge ' . $color . ' fw-bold px-4 py-3">' . e($status) . '</span>';
            })

            ->rawColumns([
                'legal_audit_status',
                'engineering_audit_status',
                'final_approval_status'
            ])

            ->make(true);
    }


    public function setStatus(Request $request)
    {
        $request->validate([
            'globalid' => ['required', 'string'],
            'status' => ['required', 'in:rejected,accepted,need_review,legal_notes'],
            'notes' => ['nullable', 'string'],
        ]);


        DB::beginTransaction();

        try {
            $user = Auth::user();

            $building = Building::where('globalid', $request->globalid)->first();

            if (!$building) {
                return response()->json([
                    'status' => false,
                    'message' => 'المبنى غير موجود',
                ], 404);
            }

            $type = null;

            if ($user->hasRole('QC/QA Engineer')) {
                $type = 'QC/QA Engineer';
            } elseif ($user->hasRole('Legal Auditor')) {
                $type = 'Legal Auditor';
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'ليس لديك صلاحية لتحديث حالة المبنى',
                ], 403);
            }

            $roleType = $type === 'QC/QA Engineer' ? 'engineer' : 'lawyer';

            $statusMap = [
                'rejected' => 'rejected_by_' . $roleType,
                'accepted' => 'accepted_by_' . $roleType,
                'need_review' => 'need_review',
                'legal_notes' => 'legal_notes'
            ];

            $statusName = $statusMap[$request->status] ?? null;

            $assessmentStatus = AssessmentStatus::where('name', $statusName)->first();

            if (!$assessmentStatus) {
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير موجودة في جدول AssessmentStatus',
                ], 422);
            }

            $buildingStatus = BuildingStatus::updateOrCreate(
                [
                    'building_id' => $building->objectid,
                    'type' => $type,
                ],
                [
                    'status_id' => $assessmentStatus->id,
                    'user_id' => Auth::id(),
                    'notes' => $request->notes,
                ]
            );

            BuildingStatusHistory::create([
                'building_id' => $building->objectid,
                'status_id' => $assessmentStatus->id,
                'user_id' => Auth::id(),
                'notes' => $request->notes,
                'type' => $type,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث حالة المبنى بنجاح',
                'data' => [
                    'building_objectid' => $building->objectid,
                    'building_globalid' => $building->globalid,
                    'type' => $type,
                    'status_id' => $assessmentStatus->id,
                    'status_name' => $assessmentStatus->name,
                    'record_id' => $buildingStatus->id,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة المبنى',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function setHousingStatus(Request $request)
    {
        $request->validate([
            'globalid' => ['required', 'string'],
            'status' => ['required', 'in:rejected,accepted,need_review,legal_notes'],
            'notes' => ['nullable', 'string'],
        ]);



        DB::beginTransaction();

        try {
            $user = Auth::user();

            $housing = HousingUnit::where('globalid', $request->globalid)->first();

            if (!$housing) {
                return response()->json([
                    'status' => false,
                    'message' => 'الوحدة السكنية غير موجودة',
                ], 404);
            }

            $type = null;

            if ($user->hasRole('QC/QA Engineer')) {
                $type = 'QC/QA Engineer';
            } elseif ($user->hasRole('Legal Auditor')) {
                $type = 'Legal Auditor';
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'ليس لديك صلاحية لتحديث حالة الوحدة',
                ], 403);
            }

            $roleType = $type === 'QC/QA Engineer' ? 'engineer' : 'lawyer';

            $statusMap = [
                'rejected' => 'rejected_by_' . $roleType,
                'accepted' => 'accepted_by_' . $roleType,
                'need_review' => 'need_review',
                'legal_notes' => 'legal_notes'
            ];
            $statusName = $statusMap[$request->status] ?? null;

            $assessmentStatus = AssessmentStatus::where('name', $statusName)->first();

            if (!$assessmentStatus) {
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير موجودة في جدول AssessmentStatus',
                ], 422);
            }

            $housingStatus = HousingStatus::updateOrCreate(
                [
                    'housing_id' => $housing->objectid,
                    'type' => $type,
                ],
                [
                    'status_id' => $assessmentStatus->id,
                    'user_id' => Auth::id(),
                    'notes' => $request->notes,
                ]
            );

            HousingStatusHistory::create([
                'housing_id' => $housing->objectid,
                'status_id' => $assessmentStatus->id,
                'user_id' => Auth::id(),
                'notes' => $request->notes,
            ]);



            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث حالة الوحدة بنجاح',
                'data' => [
                    'housing_objectid' => $housing->objectid,
                    'housing_globalid' => $housing->globalid,
                    'type' => $type,
                    'status_id' => $assessmentStatus->id,
                    'status_name' => $assessmentStatus->name,
                    'record_id' => $housingStatus->id,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة الوحدة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function assign(Request $request)
    {
        $request->validate([
            'building_ids' => ['required', 'array'],
            'building_ids.*' => ['required', 'exists:buildings,objectid'],
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'string'],
            'status_id' => ['nullable', 'exists:assessment_statuses,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->building_ids as $buildingId) {

                    $building = Building::where('objectid', $buildingId)->first();

                    if (!$building) {
                        continue;
                    }

                    AssignedAssessmentUser::updateOrCreate(
                        [
                            'building_id' => $buildingId,
                            'type' => $request->type,
                        ],
                        [
                            'user_id' => $request->user_id,
                            'manager_id' => Auth::id(),
                            'type' => $request->type,
                        ]
                    );

                    if (!$request->filled('status_id')) {
                        continue;
                    }

                    $buildingStatus = BuildingStatus::firstOrNew([
                        'building_id' => $buildingId,
                        'type' => $request->type,
                    ]);

                    $statusChanged =
                        !$buildingStatus->exists ||
                        (int) $buildingStatus->status_id !== (int) $request->status_id;

                    $buildingStatus->status_id = $request->status_id;
                    $buildingStatus->user_id = $request->user_id;
                    $buildingStatus->notes = $request->notes;
                    $buildingStatus->type = $request->type;
                    $buildingStatus->save();

                    if ($statusChanged) {
                        BuildingStatusHistory::create([
                            'building_id' => $buildingId,
                            'status_id' => $request->status_id,
                            'user_id' => Auth::id(),
                            'notes' => $request->notes,
                            'type' => $request->type,
                        ]);
                    }

                    $housings = HousingUnit::where('parentglobalid', $building->globalid)->get();


                    foreach ($housings as $housing) {
                        $housingStatus = HousingStatus::firstOrNew([
                            'housing_id' => $housing->objectid,
                            'type' => $request->type,
                        ]);

                        $housingStatusChanged = !$housingStatus->exists || (int) $housingStatus->status_id !== (int) $request->status_id;

                        $housingStatus->status_id = $request->status_id;
                        $housingStatus->user_id = $request->user_id;
                        $housingStatus->notes = $request->notes;
                        $housingStatus->type = $request->type;
                        $housingStatus->save();

                        if ($housingStatusChanged) {
                            HousingStatusHistory::create([
                                'housing_id' => $housing->objectid,
                                'status_id' => $request->status_id,
                                'user_id' => Auth::id(),
                                'notes' => $request->notes,
                                'type' => $request->type,
                            ]);
                        }
                    }
                }
            });

            return response()->json([
                'message' => 'Assignment completed successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assessmentAudit(Request $request)
    {
        if ($request->ajax()) {


            $user = Auth::user();
            $type = $user->hasRole('QC/QA Engineer') ? 'QC/QA Engineer' : ($user->hasRole('Legal Auditor') ? 'Legal Auditor' : null);

            if (!$type) {
                abort(403, 'Unauthorized');
            }
            if (!in_array($type, ['eng', 'lawyer'])) {
                abort(403, 'Unauthorized');
            }

            $statusRelation = $type === 'eng' ? 'engineerStatus.status' : 'lawyerStatus.status';

            $data = Building::with([
                'assignedUsers.user',
                $statusRelation
            ])->whereHas('assignedUsers', function ($q) use ($type, $user) {
                $q->where('type', $type)
                    ->where('user_id', $user->id);
            });

            return DataTables::of($data)
                ->addIndexColumn()

                ->editColumn('building_name', function ($row) {
                    return '<span class="text-gray-800 fw-bold">' . ($row->building_name ?? '-') . '</span>';
                })



                ->addColumn('status', function ($row) use ($type) {
                    $statusModel = $type === 'eng'
                        ? $row->engineerStatus?->status
                        : $row->lawyerStatus?->status;

                    $status = $statusModel?->label_en ?? 'Pending';
                    $statusName = strtolower($statusModel?->name ?? 'pending');


                    return '<span class="badge ' . $this->getStatusBadge($statusName) . ' fw-bold px-4 py-3">' . e($status) . '</span>';
                })

                ->addColumn('actions', function ($row) {
                    $assessmentUrl = url("/showAassessmentAudit/{$row->globalid}");

                    return '
    <div class="d-flex justify-content-end">
        <button class="btn btn-light btn-sm"
            data-kt-menu-trigger="click"
            data-kt-menu-placement="bottom-end">
            إجراءات
        </button>

        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold fs-7 w-150px py-4"
             data-kt-menu="true">

            <div class="menu-item px-3">
                <a target="_blank" href="' . $assessmentUrl . '" class="menu-link px-3">الإستبيان</a>
            </div>
         
        </div>
    </div>
    ';
                })

                ->rawColumns(['building_name', 'status', 'actions'])
                ->make(true);
        }
        $users = User::all();
        $engineers = Building::distinct('assignedto')->select('assignedto')->get();
        $owners = Building::distinct('owner_name')->select('owner_name')->get();
        $municip = Building::distinct('municipalitie')->select('municipalitie')->get();
        $neighborhoods = Building::distinct()->pluck('neighborhood');
        $assessments = Assessment::all();
        $filterName = Filter::distinct('list_name')->pluck('list_name');
        $filters = Filter::all();

        return View::make('DamageAssessment.auditBuilding', compact(
            'users',
            'neighborhoods',
            'filterName',
            'filters',
            'engineers',
            'owners',
            'municip',
            'assessments'
        ));
    }

    public function showAssessmentAudit(Request $request)
    {


        $buildingGlobalid = $request->buildingGlobalid;
        $housingGlobalid = $request->housingGlobalid;

        $building = Building::where('globalid', $request->buildingGlobalid)->first();
        $HousingUnit = HousingUnit::where('parentglobalid', $request->buildingGlobalid)->get();
        $assessments = Assessment::all();
        $buildingCurrentStatus = BuildingStatus::with('status')
            ->whereHas('building', function ($q) use ($buildingGlobalid) {
                $q->where('globalid', $buildingGlobalid);
            })
            ->first()?->status?->name;

        return View::make('DamageAssessment.assessmentAudit', compact('buildingCurrentStatus', 'housingGlobalid', 'buildingGlobalid', 'building', 'assessments', 'HousingUnit'));
    }


    public function updateInlineAssessment(Request $request)
    {
        $request->validate([
            'type' => 'required|in:building_table,housing_table',
            'globalid' => 'required|string',
            'field' => 'required|string',
            'value' => 'nullable',
        ]);

        $modelClass = $request->type === 'building_table'
            ? \App\Models\Building::class
            : \App\Models\HousingUnit::class;

        $fillable = (new $modelClass())->getFillable();

        if (!in_array($request->field, $fillable)) {
            return response()->json([
                'status' => false,
                'message' => 'هذا الحقل غير قابل للتعديل'
            ], 422);
        }

        $value = $request->value;

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        \App\Models\EditAssessment::create(
            [
                'global_id' => $request->globalid,
                'type' => $request->type,
                'field_name' => $request->field,
                'user_id' => auth()->id(),
                'field_value' => $value,

            ]

        );

        return response()->json([
            'status' => true,
            'message' => 'تم حفظ التعديل بنجاح'
        ]);
    }

    public function housingUnitAudit(Request $request)
    {
        $globalid = $request->globalid;
        $data = HousingUnit::query()->where('global_id', $globalid);

        return DataTables::of($data)

            ->addColumn('final_approval_status', function ($row) {
                return $row->final_approval_status ?? 'Pending';
            })

            ->addColumn('legal_audit_status', function ($row) {
                $status = $row->legal_audit_status ?? '-';

                if ($status === 'Rejected By Lawyer') {
                    return '<span class="badge badge-light-danger w-100 d-inline-block py-3">' . $status . '</span>';
                }

                return '<span class="badge badge-light-warning">' . $status . '</span>';
            })

            ->addColumn('engineering_audit_status', function ($row) {
                return $row->engineering_audit_status ?? '-';
            })

            ->addColumn('unit_direction', function ($row) {
                return $row->unit_direction ?? '-';
            })

            ->addColumn('owner_name', function ($row) {
                return $row->owner_name ?? '-';
            })

            ->addColumn('unit_number', function ($row) {
                return $row->housing_unit_number ?? '-';
            })

            ->addColumn('floor_number', function ($row) {
                return $row->floor_number ?? '-';
            })

            ->addColumn('damage_status', function ($row) {
                return $row->unit_damage_status ?? '-';
            })

            ->addColumn('unit_type', function ($row) {
                return $row->housing_unit_type ?? '-';
            })

            ->rawColumns(['edit', 'legal_audit_status'])
            ->make(true);
    }


    public function buildingHistory(Request $request)
    {
        $building = Building::where('globalid', $request->globalid)->first();

        if (!$building) {
            return response()->json([
                'status' => false,
                'history' => []
            ]);
        }

        $canDelete = auth()->user()->hasAnyRole(['Database Officer', 'Auditing Supervisor']);

        $history = BuildingStatusHistory::with(['user.roles', 'status'])
            ->where('building_id', $building->objectid)
            ->latest()
            ->get()
            ->map(function ($item) use ($canDelete) {
                $statusName = $item->status->name ?? '-';
                $statusLabel = $item->status->label_en ?? $statusName;
                $roleName = $item->user?->roles?->first()?->name ?? '-';

                return [
                    'id' => $item->id,
                    'status_name' => '<span class="' . $this->getStatusBadge($statusName, $roleName) . '">' . e($statusLabel) . '</span>',
                    'user_name' => $item->user->name ?? '-',
                    'role_name' => $roleName,
                    'notes' => $item->notes ?? '-',
                    'created_at' => $item->created_at ? $item->created_at->format('Y-m-d h:i A') : '-',
                    'can_delete' => $canDelete,
                ];
            });

        return response()->json([
            'status' => true,
            'history' => $history
        ]);
    }

    public function housingHistory(Request $request)
    {
        $housing = HousingUnit::where('globalid', $request->globalid)->first();

        if (!$housing) {
            return [];
        }

        return HousingStatusHistory::with(['user.roles', 'assessment_status'])
            ->where('housing_id', $housing->objectid)
            ->latest()
            ->get()
            ->map(function ($item) {
                $statusName = $item->assessment_status->name ?? '-';
                $statusLabel = $item->assessment_status->label_en ?? $statusName;
                $roleName = $item->user?->roles?->first()?->name ?? '-';

                return [
                    'status_name' => '<span class="' . $this->getStatusBadge($statusName, $roleName) . '">' . e($statusLabel) . '</span>',
                    'user_name' => $item->user->name ?? '-',
                    'role_name' => $roleName,
                    'notes' => $item->notes ?? '-',
                    'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                ];
            });
    }

    private function getStatusBadge($statusName, $role = null)
    {
        return match ($statusName) {
            'assigned_to_lawyer' => 'badge badge-light-primary fw-bold',
            'assigned_to_engineer' => 'badge badge-light-primary fw-bold',
            'accepted_by_engineer',
            'accepted' => 'badge badge-light-success fw-bold',
            'rejected_by_engineer',
            'rejected' => 'badge badge-light-danger fw-bold',
            'need_review' => 'badge badge-light-warning fw-bold',
            'legal_notes' => 'badge badge-light-primary fw-bold',
            default => 'badge badge-light-secondary fw-bold',
        };
    }
    public function getEditableNote(Request $request)
    {
        $request->validate([
            'type' => 'required|in:building,housing',
            'globalid' => 'required|string',
            'note_id' => 'nullable|integer',
        ]);

        $type = $request->type;
        $globalid = $request->globalid;
        $noteId = $request->note_id;

        if ($type === 'building') {
            $building = Building::where('globalid', $globalid)->first();

            if (!$building) {
                return response()->json([
                    'message' => 'المبنى غير موجود'
                ], 404);
            }

            $hasFinalApprove = BuildingStatusHistory::where('building_id', $building->objectid)
                ->whereHas('status', function ($q) {
                    $q->where('name', 'final_approval');
                })
                ->exists();

            $query = BuildingStatusHistory::with(['status', 'user'])
                ->where('building_id', $building->objectid)
                ->whereNotNull('notes')
                ->where('notes', '!=', '');

            if ($noteId) {
                $query->where('id', $noteId);
            } else {
                $query->latest('id');
            }

            $note = $query->first();

            /*  if (!$note) {
                 return response()->json([
                     'message' => 'لا توجد ملاحظة متاحة'
                 ], 404);
             }
  */
            return response()->json([
                'id' => $note->id,
                'notes' => $note->notes,
                'has_final_approve' => $hasFinalApprove,
                'status_name' => optional($note->status)->label ?? optional($note->status)->name ?? '-',
                'user_name' => optional($note->user)->name ?? '-',
                'created_at' => optional($note->created_at)?->format('Y-m-d H:i'),
            ]);
        }

        if ($type === 'housing') {
            $housing = HousingUnit::where('globalid', $globalid)->first();

            if (!$housing) {
                return response()->json([
                    'message' => 'الوحدة السكنية غير موجودة'
                ], 404);
            }

            $hasFinalApprove = HousingStatusHistory::where('housing_id', $housing->objectid)
                ->whereHas('assessment_status', function ($q) {
                    $q->where('name', 'final_approval');
                })
                ->exists();

            $query = HousingStatusHistory::with(['assessment_status', 'user'])
                ->where('housing_id', $housing->objectid)
                ->whereNotNull('notes')
                ->where('notes', '!=', '');

            if ($noteId) {
                $query->where('id', $noteId);
            } else {
                $query->latest('id');
            }

            $note = $query->first();

            if (!$note) {
                return response()->json([
                    'message' => 'لا توجد ملاحظة متاحة'
                ], 404);
            }

            return response()->json([
                'id' => $note->id,
                'notes' => $note->notes,
                'has_final_approve' => $hasFinalApprove,
                'status_name' => optional($note->status)->label ?? optional($note->status)->name ?? '-',
                'user_name' => optional($note->user)->name ?? '-',
                'created_at' => optional($note->created_at)?->format('Y-m-d H:i'),
            ]);
        }
    }


    public function updateNote(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'type' => 'required|in:building,housing',
            'notes' => 'nullable|string',
        ]);

        $id = $request->id;
        $type = $request->type;
        $notes = trim((string) $request->notes);

        if ($type === 'building') {
            $note = BuildingStatusHistory::find($id);

            if (!$note) {
                return response()->json([
                    'message' => 'الملاحظة غير موجودة'
                ], 404);
            }

            $hasFinalApprove = BuildingStatusHistory::where('building_id', $note->building_id)
                ->whereHas('status', function ($q) {
                    $q->where('name', 'final_approval');
                })
                ->exists();

            if ($hasFinalApprove) {
                return response()->json([
                    'message' => 'لا يمكن تعديل الملاحظة لأن الاعتماد النهائي موجود'
                ], 422);
            }

            $note->notes = $notes;
            $note->save();

            return response()->json([
                'message' => 'تم تحديث ملاحظة المبنى بنجاح'
            ]);
        }

        if ($type === 'housing') {
            $note = HousingStatusHistory::find($id);

            if (!$note) {
                return response()->json([
                    'message' => 'الملاحظة غير موجودة'
                ], 404);
            }

            $hasFinalApprove = HousingStatusHistory::where('housing_id', $note->housing_id)
                ->whereHas('assessment_status', function ($q) {
                    $q->where('name', 'final_approval');
                })
                ->exists();

            if ($hasFinalApprove) {
                return response()->json([
                    'message' => 'لا يمكن تعديل الملاحظة لأن الاعتماد النهائي موجود'
                ], 422);
            }

            $note->notes = $notes;
            $note->save();

            return response()->json([
                'message' => 'تم تحديث ملاحظة الوحدة بنجاح'
            ]);
        }
    }
    public function deleteHistory(Request $request)
    {
        $history = BuildingStatusHistory::with('status')->find($request->id);

        if (!$history) {
            return response()->json([
                'status' => false,
                'message' => 'السجل غير موجود'
            ]);
        }

        // السماح فقط لهذين الدورين
        if (!auth()->user()->hasAnyRole(['Database Officer', 'Auditing Supervisor'])) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بحذف هذا السجل'
            ], 403);
        }

        // اختياري: منع حذف آخر حالة
        $isLast = BuildingStatusHistory::where('building_id', $history->building_id)->count() <= 1;
        if ($isLast) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف آخر حالة'
            ]);
        }

        // اختياري: منع حذف الحالة النهائية
        if (in_array($history->status->name ?? '', ['final_approval', 'final_rejected'])) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف الحالة النهائية'
            ]);
        }

        $history->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف السجل بنجاح'
        ]);
    }

}
