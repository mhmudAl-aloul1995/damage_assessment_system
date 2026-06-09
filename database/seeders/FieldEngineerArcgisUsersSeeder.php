<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldEngineerArcgisUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{ID: string, AssignedTo: string, NameEnglish: string, NameArabic: string, Email: string, ContactNumber: string}>
     */
    private const RECORDS = [
        ['ID' => '404277543', 'AssignedTo' => 'Ahmed.Muhhana', 'NameEnglish' => 'Ahmed Mohammed Abed Alrahmen Muhhana', 'NameArabic' => "\u{623}\u{62D}\u{645}\u{62F} \u{645}\u{62D}\u{645}\u{62F} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{631}\u{62D}\u{645}\u{646} \u{645}\u{647}\u{646}\u{627}", 'Email' => 'ahmedmuhanna98@gmail.com', 'ContactNumber' => '594161818'],
        ['ID' => '976066191', 'AssignedTo' => 'Ahmed.Asqool', 'NameEnglish' => 'Ahmed Omar Abbas Asqool', 'NameArabic' => "\u{623}\u{62D}\u{645}\u{62F} \u{639}\u{645}\u{631} \u{639}\u{628}\u{627}\u{633} \u{639}\u{633}\u{642}\u{648}\u{644}", 'Email' => 'aasqool81@gmail.com', 'ContactNumber' => '567237520'],
        ['ID' => '410109441', 'AssignedTo' => 'Ahmed.Abudaqqa', 'NameEnglish' => 'Ahmed Sh. Ali Abu Daqqa', 'NameArabic' => "\u{627}\u{62D}\u{645}\u{62F} \u{634}\u{631}\u{64A}\u{641} \u{639}\u{644}\u{64A} \u{627}\u{628}\u{648} \u{62F}\u{642}\u{629}", 'Email' => 'ahmedabudaqqa7@gmail.com', 'ContactNumber' => '568236661'],
        ['ID' => '407364017', 'AssignedTo' => 'Amjed.Nada', 'NameEnglish' => 'Amjed M. I. Nada', 'NameArabic' => "\u{627}\u{645}\u{62C}\u{62F} \u{645}\u{62D}\u{645}\u{648}\u{62F} \u{625}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{623}\u{628}\u{648} \u{646}\u{62F}\u{64A}", 'Email' => 'amjadabunada9@gmail.com', 'ContactNumber' => '594381872'],
        ['ID' => '404547499', 'AssignedTo' => 'Bilal.AbuGhalaa', 'NameEnglish' => 'Bilal A.M AbuGhalaa', 'NameArabic' => "\u{628}\u{644}\u{627}\u{644} \u{627}\u{643}\u{631}\u{645} \u{645}\u{62D}\u{645}\u{62F} \u{627}\u{628}\u{648} \u{63A}\u{627}\u{644}\u{649}", 'Email' => 'ce.bilalgh@gmail.com', 'ContactNumber' => '594831500'],
        ['ID' => '932079502', 'AssignedTo' => 'Emad.Shahada', 'NameEnglish' => 'Emad M.M Shahada', 'NameArabic' => "\u{639}\u{645}\u{627}\u{62F} \u{645}\u{62D}\u{645}\u{648}\u{62F} \u{645}\u{62D}\u{645}\u{62F} \u{634}\u{62D}\u{627}\u{62F}\u{629}", 'Email' => 'emad_shehada@hotmail.com', 'ContactNumber' => '599853112'],
        ['ID' => '403699762', 'AssignedTo' => 'Hamed.Alnajjar', 'NameEnglish' => 'Hamed M.A. Alnajjar', 'NameArabic' => "\u{62D}\u{627}\u{645}\u{62F} \u{645}\u{62D}\u{645}\u{648}\u{62F} \u{639}\u{628}\u{62F} \u{62D}\u{648}\u{64A}\u{637}\u{64A} \u{627}\u{644}\u{646}\u{62C}\u{627}\u{631}", 'Email' => 'hamednjr63@gmail.com', 'ContactNumber' => '592386896'],
        ['ID' => '900148545', 'AssignedTo' => 'Hasan.Almasri', 'NameEnglish' => 'Hassan K. A. Al-Massry', 'NameArabic' => "\u{62D}\u{633}\u{646}  \u{643}\u{645}\u{627}\u{644} \u{639}\u{627}\u{645}\u{631} \u{627}\u{644}\u{645}\u{635}\u{631}\u{64A}", 'Email' => 'hasankamm6@gmail.com', 'ContactNumber' => '599384814'],
        ['ID' => '800889057', 'AssignedTo' => 'Hatim.Matar', 'NameEnglish' => 'Hatim Mohammad Hamdan Matar', 'NameArabic' => "\u{62D}\u{627}\u{62A}\u{645} \u{645}\u{62D}\u{645}\u{62F} \u{62D}\u{645}\u{62F}\u{627}\u{646} \u{645}\u{637}\u{631}", 'Email' => 'hmattar1970@hotmaim.com', 'ContactNumber' => '599867747'],
        ['ID' => '400662938', 'AssignedTo' => 'Hessin.Naeim', 'NameEnglish' => 'Hesssin J. E. Naeim', 'NameArabic' => "\u{62D}\u{633}\u{64A}\u{646} \u{62C}\u{644}\u{627}\u{644} \u{627}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{646}\u{639}\u{64A}\u{645}", 'Email' => 'architect.husainnaim@gmail.com', 'ContactNumber' => '592395180'],
        ['ID' => '406966812', 'AssignedTo' => 'Ibrahim.Alhallaq', 'NameEnglish' => 'Ibrahim Kh. Ab. Al-Haalq', 'NameArabic' => "\u{627}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{62E}\u{627}\u{644}\u{62F} \u{639}\u{628}\u{62F}\u{627}\u{644}\u{631}\u{624}\u{648}\u{641} \u{627}\u{644}\u{62D}\u{644}\u{627}\u{642}", 'Email' => 'ibh122000@gmail.com', 'ContactNumber' => '598025502'],
        ['ID' => '410529473', 'AssignedTo' => 'Ibrahim.Alqedra', 'NameEnglish' => 'Ibrahim shawqi alqedra', 'NameArabic' => "\u{625}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{634}\u{648}\u{642}\u{64A} \u{645}\u{62D}\u{645}\u{62F} \u{627}\u{644}\u{642}\u{62F}\u{631}\u{629}", 'Email' => 'qc.qedra@hotmail.com', 'ContactNumber' => '598800719'],
        ['ID' => '404149486', 'AssignedTo' => 'Ismail.Alshakh.Ahmed', 'NameEnglish' => 'ISMAIL H. I. ALSHAKH AHMED', 'NameArabic' => "\u{627}\u{633}\u{645}\u{627}\u{639}\u{64A}\u{644} \u{62D}\u{627}\u{632}\u{645} \u{627}\u{633}\u{645}\u{627}\u{639}\u{64A}\u{644} \u{627}\u{644}\u{634}\u{64A}\u{62E} \u{627}\u{62D}\u{645}\u{62F}", 'Email' => 'ismhazem1998@gmail.com', 'ContactNumber' => '597248757'],
        ['ID' => '802723411', 'AssignedTo' => 'Kholood.Othman', 'NameEnglish' => 'KHOLOOD S. A. OTHMAN', 'NameArabic' => "\u{62E}\u{644}\u{648}\u{62F} \u{635}\u{627}\u{62F}\u{642} \u{627}\u{644}\u{639}\u{628}\u{62F} \u{639}\u{62B}\u{645}\u{627}\u{646}", 'Email' => 'kho.daw2022@gmail.com', 'ContactNumber' => '598779493'],
        ['ID' => '803819531', 'AssignedTo' => 'Merwad.Almassri', 'NameEnglish' => 'Merwad M. S. Al- massri', 'NameArabic' => "\u{645}\u{631}\u{648}\u{627}\u{62F} \u{645}\u{62D}\u{645}\u{62F} \u{633}\u{644}\u{64A}\u{645} \u{627}\u{644}\u{645}\u{635}\u{631}\u{64A}", 'Email' => 'Mamola455@gmail.com', 'ContactNumber' => '567016661'],
        ['ID' => '901672147', 'AssignedTo' => 'Mohamed.Ghaith', 'NameEnglish' => 'Mohamed M.A Ghaith', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{645}\u{62D}\u{645}\u{648}\u{62F} \u{627}\u{62D}\u{645}\u{62F} \u{63A}\u{64A}\u{62B}", 'Email' => 'g.01200100@gmail.com', 'ContactNumber' => '592939812'],
        ['ID' => '456901503', 'AssignedTo' => 'Mohammed.Alhaj', 'NameEnglish' => 'Mohammed  Al Hajj', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{639}\u{632}\u{64A}\u{632} \u{627}\u{644}\u{62D}\u{627}\u{62C}", 'Email' => 'hamoodyalsoryhaj@gmail.com', 'ContactNumber' => '592640034'],
        ['ID' => '900238437', 'AssignedTo' => 'Mohammed.Hammad', 'NameEnglish' => 'Mohammed Hassan Mohammed Hammad', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{62D}\u{633}\u{646} \u{645}\u{62D}\u{645}\u{62F} \u{62D}\u{645}\u{627}\u{62F}", 'Email' => 'mohammdhammad74@gmail.com', 'ContactNumber' => '599884482'],
        ['ID' => '801043050', 'AssignedTo' => 'Mohanad.Muamar', 'NameEnglish' => 'Mohanad Suliman MUAMAR', 'NameArabic' => "\u{645}\u{647}\u{646}\u{62F} \u{633}\u{644}\u{64A}\u{645}\u{627}\u{646} \u{62D}\u{633}\u{64A}\u{646} \u{645}\u{639}\u{645}\u{631}", 'Email' => 'eng.mohanad85@hotmail.com', 'ContactNumber' => '592500760'],
        ['ID' => '800843039', 'AssignedTo' => 'Mohand.Alshaer', 'NameEnglish' => 'Mohand N. M. Al-Shaer', 'NameArabic' => "\u{645}\u{647}\u{646}\u{62F} \u{646}\u{648}\u{627}\u{641} \u{645}\u{635}\u{637}\u{641}\u{649} \u{627}\u{644}\u{634}\u{627}\u{639}\u{631}", 'Email' => 'mohanad.alshaer84@gmail.com', 'ContactNumber' => '567251572'],
        ['ID' => '802720227', 'AssignedTo' => 'Mohemmed.Isleem', 'NameEnglish' => 'Mohemmed Saleem Hammouda isleem', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{633}\u{644}\u{64A}\u{645} \u{62D}\u{645}\u{648}\u{62F}\u{629} \u{633}\u{644}\u{64A}\u{645}", 'Email' => 'moh9160250@gmial.com', 'ContactNumber' => '599160250'],
        ['ID' => '803663152', 'AssignedTo' => 'Mustafa.Baroud', 'NameEnglish' => 'Mustafa Ali Mustafa Baroud', 'NameArabic' => "\u{645}\u{635}\u{637}\u{641}\u{649} \u{639}\u{644}\u{64A} \u{645}\u{635}\u{637}\u{641}\u{649} \u{628}\u{627}\u{631}\u{648}\u{62F}", 'Email' => 'abu.emaad.1964@gmail.com', 'ContactNumber' => '599680521'],
        ['ID' => '801340795', 'AssignedTo' => 'Osama.Alharazeen', 'NameEnglish' => 'Osama A. S. Al- Harazeen', 'NameArabic' => "\u{623}\u{633}\u{627}\u{645}\u{629} \u{627}\u{644}\u{639}\u{628}\u{62F} \u{633}\u{627}\u{644}\u{645} \u{627}\u{644}\u{62D}\u{631}\u{627}\u{632}\u{64A}\u{646}", 'Email' => 'osama.a.s.alharazeen@gmail.com', 'ContactNumber' => '599366253'],
        ['ID' => '401272398', 'AssignedTo' => 'Osama.Alazeez', 'NameEnglish' => 'Osama H M Abed Alazeez', 'NameArabic' => "\u{623}\u{633}\u{627}\u{645}\u{629} \u{62D}\u{633}\u{627}\u{645} \u{645}\u{635}\u{637}\u{641}\u{649} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{639}\u{632}\u{64A}\u{632}", 'Email' => 'os0597060008@gmail.com', 'ContactNumber' => '597060008'],
        ['ID' => '404171316', 'AssignedTo' => 'Sara.Abumostafa', 'NameEnglish' => 'Sarah Ziad Ahmed Abu Mustafa', 'NameArabic' => "\u{633}\u{627}\u{631}\u{629} \u{632}\u{64A}\u{627}\u{62F} \u{627}\u{62D}\u{645}\u{62F} \u{627}\u{628}\u{648} \u{645}\u{635}\u{637}\u{641}\u{649}", 'Email' => 'saraziadmostafa@gmail.com', 'ContactNumber' => '595778691'],
        ['ID' => '407765668', 'AssignedTo' => 'Abdelazeem.Aburass', 'NameEnglish' => 'Abdelazeem U. Aburass', 'NameArabic' => "\u{639}\u{628}\u{62F} \u{627}\u{644}\u{639}\u{638}\u{64A}\u{645} \u{627}\u{633}\u{627}\u{645}\u{647} \u{639}\u{628}\u{62F}\u{627}\u{644}\u{639}\u{638}\u{64A}\u{645} \u{627}\u{628}\u{648} \u{631}\u{627}\u{633}", 'Email' => 'abdelazeem.u.aburass@gmail.com', 'ContactNumber' => '599355610'],
        ['ID' => '400762704', 'AssignedTo' => 'Abdallah.Alnajaar', 'NameEnglish' => 'Abdullah Mohammed Khalid Al-Najjar', 'NameArabic' => "\u{639}\u{628}\u{62F}\u{627}\u{644}\u{644}\u{647} \u{645}\u{62D}\u{645}\u{62F} \u{62E}\u{627}\u{644}\u{62F} \u{627}\u{644}\u{646}\u{62C}\u{627}\u{631}", 'Email' => 'abdallahaln56@gmail.com', 'ContactNumber' => '599606017'],
        ['ID' => '802292805', 'AssignedTo' => 'Abed.Yassen', 'NameEnglish' => 'Abed Allah Yassen', 'NameArabic' => "\u{639}\u{628}\u{62F} \u{627}\u{644}\u{644}\u{647} \u{641}\u{624}\u{627}\u{62F} \u{62C}\u{645}\u{64A}\u{644} \u{64A}\u{627}\u{633}\u{64A}\u{646}", 'Email' => 'Abd-f-y@hotmail.com', 'ContactNumber' => '597274267'],
        ['ID' => '803307669', 'AssignedTo' => 'Amal.Busafia', 'NameEnglish' => 'AMAL S . A BUSAFIA', 'NameArabic' => "\u{623}\u{645}\u{627}\u{644} \u{634}\u{648}\u{643}\u{62A} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{631}\u{62D}\u{645}\u{646} \u{627}\u{628}\u{648} \u{635}\u{641}\u{64A}\u{629}", 'Email' => 'Eng.2010@outlook.sa', 'ContactNumber' => '597231643'],
        ['ID' => '802292318', 'AssignedTo' => 'Aya.Shublaq', 'NameEnglish' => 'Aya A. M. Shublaq', 'NameArabic' => "\u{627}\u{64A}\u{629} \u{623}\u{64A}\u{645}\u{646} \u{645}\u{62D}\u{645}\u{62F} \u{634}\u{628}\u{644}\u{627}\u{642}", 'Email' => 'eng.aya.ayman.89@gmail.com', 'ContactNumber' => '598258450'],
        ['ID' => '403006828', 'AssignedTo' => 'Bashar.Abdalbari', 'NameEnglish' => 'BASHAR J. F. ABDALBARI', 'NameArabic' => "\u{628}\u{634}\u{627}\u{631} \u{62C}\u{645}\u{627}\u{644} \u{641}\u{631}\u{62C} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{628}\u{627}\u{631}\u{64A}", 'Email' => 'BasharJamal23@gmail.com', 'ContactNumber' => '598675716'],
        ['ID' => '905391124', 'AssignedTo' => 'Belal.AbuKhaleifa', 'NameEnglish' => 'Belal Mohammed Abed Abu Khaleifa', 'NameArabic' => "\u{628}\u{644}\u{627}\u{644} \u{645}\u{62D}\u{645}\u{62F} \u{639}\u{628}\u{62F} \u{627}\u{628}\u{648}\u{62E}\u{644}\u{64A}\u{641}\u{629}", 'Email' => 'be-eng-1980@hotmail.com', 'ContactNumber' => '0592288970 _ 0567427182'],
        ['ID' => '801706334', 'AssignedTo' => 'Dina.Alkhatib', 'NameEnglish' => 'DINA H. A. ALKHATIB', 'NameArabic' => "\u{62F}\u{64A}\u{646}\u{627} \u{62D}\u{64A}\u{62F}\u{631} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{62E}\u{637}\u{64A}\u{628}", 'Email' => 'arch.d.hedar@gmail.com', 'ContactNumber' => '599902269'],
        ['ID' => '404015869', 'AssignedTo' => 'Esraa.AlManasra', 'NameEnglish' => 'Esraa Al Manasra', 'NameArabic' => "\u{627}\u{633}\u{631}\u{627}\u{621} \u{62D}\u{645}\u{627}\u{62F} \u{627}\u{644}\u{645}\u{646}\u{627}\u{635}\u{631}\u{629}", 'Email' => 'esraamanasra32@gmail.com', 'ContactNumber' => '569441194'],
        ['ID' => '903493518', 'AssignedTo' => 'Eyad.Elwan', 'NameEnglish' => 'Eyad S. M. Elwan', 'NameArabic' => "\u{627}\u{64A}\u{627}\u{62F} \u{633}\u{639}\u{62F}\u{64A} \u{645}\u{62D}\u{645}\u{62F} \u{639}\u{644}\u{648}\u{627}\u{646}", 'Email' => 'eyadolwan1965@gmail.com', 'ContactNumber' => '592605646'],
        ['ID' => '900883778', 'AssignedTo' => 'Fawzy.Ghannam', 'NameEnglish' => 'Fawzy  F. F. Ghannam', 'NameArabic' => "\u{641}\u{648}\u{632}\u{64A} \u{641}\u{627}\u{631}\u{633} \u{641}\u{648}\u{632}\u{64A} \u{63A}\u{646}\u{627}\u{645}", 'Email' => 'fawzy-w0@hotmail.com', 'ContactNumber' => '599606017'],
        ['ID' => '804382299', 'AssignedTo' => 'Fayez.Abusafia', 'NameEnglish' => 'FAYEZ S. A. ABUSAFIA', 'NameArabic' => "\u{641}\u{627}\u{64A}\u{632} \u{634}\u{648}\u{643}\u{62A} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{631}\u{62D}\u{645}\u{646}  \u{627}\u{628}\u{648} \u{635}\u{641}\u{64A}\u{629}", 'Email' => 'Fay-z-92@outlook.com', 'ContactNumber' => '592663057'],
        ['ID' => '412350464', 'AssignedTo' => 'Hasan.Alnakhal', 'NameEnglish' => 'HASAN D. H. ALNAKHAL', 'NameArabic' => "\u{62D}\u{633}\u{646} \u{636}\u{631}\u{627}\u{631} \u{62D}\u{633}\u{646} \u{627}\u{644}\u{646}\u{62E}\u{627}\u{644}", 'Email' => 'h.80200@gmail.com', 'ContactNumber' => '599207973'],
        ['ID' => '903630804', 'AssignedTo' => 'Hassan.AlJabry', 'NameEnglish' => 'Hassan M. H. Al-Jabry', 'NameArabic' => "\u{62D}\u{633}\u{646} \u{645}\u{62D}\u{645}\u{62F} \u{62D}\u{633}\u{646} \u{627}\u{644}\u{62C}\u{639}\u{628}\u{631}\u{64A}", 'Email' => 'aljabary.7asan@gmail.com', 'ContactNumber' => '595711001'],
        ['ID' => '802468371', 'AssignedTo' => 'Heba.Alkhalili', 'NameEnglish' => 'HEBA B. R. ALKHALILI', 'NameArabic' => "\u{647}\u{628}\u{629} \u{628}\u{633}\u{627}\u{645} \u{631}\u{628}\u{62D}\u{64A} \u{627}\u{644}\u{62E}\u{644}\u{64A}\u{644}\u{64A}", 'Email' => 'eng.haboosh.89@hotmail.com', 'ContactNumber' => '599909906'],
        ['ID' => '905280012', 'AssignedTo' => 'Irahim.Alnajjar', 'NameEnglish' => 'IRAHIM J. I. ALNAJJAR', 'NameArabic' => "\u{627}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{62C}\u{645}\u{627}\u{644} \u{627}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{627}\u{644}\u{646}\u{62C}\u{627}\u{631}", 'Email' => 'Jamalcompany80@gmail.com', 'ContactNumber' => '599207256'],
        ['ID' => '801170523', 'AssignedTo' => 'Khaled.Al-Daour', 'NameEnglish' => 'Khaled Zaki Ebrahim Al-Daour', 'NameArabic' => "\u{62E}\u{627}\u{644}\u{62F} \u{632}\u{643}\u{649} \u{627}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{627}\u{644}\u{62F}\u{627}\u{639}\u{648}\u{631}", 'Email' => 'eng.Khaledzaki@hotmail.com', 'ContactNumber' => '595550841'],
        ['ID' => '402545818', 'AssignedTo' => 'Khaled.Alyazji', 'NameEnglish' => 'Khalid A.K Alyazjaa', 'NameArabic' => "\u{62E}\u{627}\u{644}\u{62F} \u{627}\u{62F}\u{647}\u{645} \u{62E}\u{627}\u{644}\u{62F} \u{627}\u{644}\u{64A}\u{627}\u{632}\u{62C}\u{649}", 'Email' => 'khaledaalyazji@gmail.com', 'ContactNumber' => '593256230'],
        ['ID' => '401866959', 'AssignedTo' => 'Lamis.Abuzaina', 'NameEnglish' => 'LAMIS A. A. ABUZAINA', 'NameArabic' => "\u{644}\u{645}\u{64A}\u{633} \u{639}\u{644}\u{627}\u{621} \u{639}\u{644}\u{64A} \u{627}\u{628}\u{648}\u{632}\u{64A}\u{646}\u{629}", 'Email' => 'Lamiszaina36@gmail.com', 'ContactNumber' => '595819284'],
        ['ID' => '801009804', 'AssignedTo' => 'Lamyaa.Mousa', 'NameEnglish' => 'LAMYAA H. M. MOUSA', 'NameArabic' => "\u{644}\u{645}\u{64A}\u{627}\u{621} \u{62D}\u{64A}\u{62F}\u{631} \u{645}\u{62D}\u{645}\u{62F} \u{645}\u{648}\u{633}\u{649}", 'Email' => 'arc.lamyaa@gmail.com', 'ContactNumber' => '567579606'],
        ['ID' => '931082895', 'AssignedTo' => 'Mahmmoud.Rajab', 'NameEnglish' => 'Mahmmoud R. A. Rajab', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{648}\u{62F} \u{631}\u{634}\u{627}\u{62F} \u{639}\u{637}\u{627} \u{631}\u{62C}\u{628}", 'Email' => 'mahmoudrjb@hotmail.com', 'ContactNumber' => '599698238'],
        ['ID' => '803498336', 'AssignedTo' => 'Mahmoud.Alkhaldi', 'NameEnglish' => 'Mahmoud Tayseer Alkhaldi', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{648}\u{62F} \u{62A}\u{64A}\u{633}\u{64A}\u{631} \u{645}\u{62D}\u{645}\u{648}\u{62F} \u{627}\u{644}\u{62E}\u{627}\u{644}\u{62F}\u{64A}", 'Email' => 'arch.mahmoud.alkhaldi@gmail.com', 'ContactNumber' => '593991220'],
        ['ID' => '403697311', 'AssignedTo' => 'Marah.Dwaima', 'NameEnglish' => 'Marah Sameer Kamel Dwaima', 'NameArabic' => "\u{645}\u{631}\u{62D} \u{633}\u{645}\u{64A}\u{631} \u{643}\u{627}\u{645}\u{644} \u{62F}\u{648}\u{64A}\u{645}\u{629}", 'Email' => 'marahdwaima2@gmail.com', 'ContactNumber' => '592095814'],
        ['ID' => '404581993', 'AssignedTo' => 'Mohamed.Tafish', 'NameEnglish' => 'Mohamed Khalid Ibrahim Tafish', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{62E}\u{627}\u{644}\u{62F} \u{627}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{637}\u{627}\u{641}\u{634}", 'Email' => 'Mohkh1999.t@gmail.com', 'ContactNumber' => '599929882'],
        ['ID' => '803164474', 'AssignedTo' => 'Mohammed.Elhaloul', 'NameEnglish' => 'MOHAMMED A. I. ELHALOUL', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{623}\u{62D}\u{645}\u{62F} \u{627}\u{628}\u{631}\u{627}\u{647}\u{64A}\u{645} \u{627}\u{644}\u{647}\u{644}\u{648}\u{644}", 'Email' => 'mohammed_242634@hotmail.com', 'ContactNumber' => '597242634'],
        ['ID' => '909098782', 'AssignedTo' => 'Mohammed.Alsdudi', 'NameEnglish' => 'MOHAMMED M. A. ALSDUDI', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{645}\u{633}\u{639}\u{648}\u{62F} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{631}\u{62D}\u{645}\u{646} \u{627}\u{644}\u{633}\u{62F}\u{648}\u{62F}\u{64A}", 'Email' => 'eng.m.sdudi@hotmail.com', 'ContactNumber' => '597333564'],
        ['ID' => '407117902', 'AssignedTo' => 'Mohammed.Kassab', 'NameEnglish' => 'Mohammed Sami Eshaq Kassab', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{633}\u{627}\u{645}\u{64A} \u{625}\u{633}\u{62D}\u{627}\u{642} \u{643}\u{633}\u{627}\u{628}", 'Email' => 'Mohammedkassab1231@gmail.com', 'ContactNumber' => '597485225'],
        ['ID' => '802225698', 'AssignedTo' => 'Mohammed.Lolo', 'NameEnglish' => 'Mohammed Z. M. Lolo', 'NameArabic' => "\u{645}\u{62D}\u{645}\u{62F} \u{632}\u{643}\u{631}\u{64A}\u{627} \u{645}\u{62D}\u{645}\u{648}\u{62F} \u{644}\u{648}\u{644}\u{648}", 'Email' => 'eng.mzak89@gmail.com', 'ContactNumber' => '599486307'],
        ['ID' => '946677226', 'AssignedTo' => 'Mojahed.Faraht', 'NameEnglish' => 'Mojahed Y. SH. Faraht', 'NameArabic' => "\u{645}\u{62C}\u{627}\u{647}\u{62F} \u{64A}\u{648}\u{633}\u{641} \u{634}\u{639}\u{628}\u{627}\u{646} \u{641}\u{631}\u{62D}\u{627}\u{62A}", 'Email' => 'eng.mojahed2010@hotmail.com', 'ContactNumber' => '568430330'],
        ['ID' => '915543557', 'AssignedTo' => 'Oday.Elhalimy', 'NameEnglish' => 'ODAY M. T. ELHALIMY', 'NameArabic' => "\u{639}\u{62F}\u{64A} \u{645}\u{62D}\u{645}\u{62F} \u{633}\u{639}\u{64A}\u{62F} \u{62A}\u{648}\u{641}\u{64A}\u{642} \u{627}\u{644}\u{62D}\u{644}\u{64A}\u{645}\u{64A}", 'Email' => 'elhalimy@Gmail.com', 'ContactNumber' => '599993845'],
        ['ID' => '403775463', 'AssignedTo' => 'Osama.AboAli', 'NameEnglish' => 'Osama Samir khaled abo ali', 'NameArabic' => "\u{627}\u{633}\u{627}\u{645}\u{629} \u{633}\u{645}\u{64A}\u{631} \u{62E}\u{627}\u{644}\u{62F} \u{623}\u{628}\u{648}\u{639}\u{644}\u{64A}", 'Email' => 'osama.saa.2017@gmail.com', 'ContactNumber' => '597701087'],
        ['ID' => '801009556', 'AssignedTo' => 'Rania.Mohana', 'NameEnglish' => 'RANIA N. H. MOHANA', 'NameArabic' => "\u{631}\u{627}\u{646}\u{64A}\u{627} \u{646}\u{62F}\u{64A}\u{645} \u{62D}\u{633}\u{646}\u{64A} \u{645}\u{647}\u{646}\u{627}", 'Email' => 'ranianadeem7@gmail.com', 'ContactNumber' => '599266075'],
        ['ID' => '400591194', 'AssignedTo' => 'Shrouq.Alrayes', 'NameEnglish' => 'SHROUQ N. B. ALRAYES', 'NameArabic' => "\u{634}\u{631}\u{648}\u{642} \u{646}\u{627}\u{626}\u{644} \u{628}\u{643}\u{64A}\u{631} \u{627}\u{644}\u{631}\u{64A}\u{633}", 'Email' => 'shouroq.n.alrayes@gmail.com', 'ContactNumber' => '567635995'],
        ['ID' => '801773987', 'AssignedTo' => 'Thekrayat.Saadat', 'NameEnglish' => 'THEKRAYAT A. A. SAADAT', 'NameArabic' => "\u{630}\u{643}\u{631}\u{64A}\u{627}\u{62A} \u{623}\u{62D}\u{645}\u{62F} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{643}\u{631}\u{64A}\u{645} \u{627}\u{644}\u{633}\u{639}\u{62F}\u{627}\u{62A}", 'Email' => 'sa8017739872020dat@gmail.com', 'ContactNumber' => '598274708'],
        ['ID' => '404586042', 'AssignedTo' => 'Yousef.Hamooda', 'NameEnglish' => 'Yousef A. M. Hamooda', 'NameArabic' => "\u{64A}\u{648}\u{633}\u{641} \u{639}\u{628}\u{62F} \u{627}\u{644}\u{647}\u{627}\u{62F}\u{64A} \u{645}\u{62D}\u{645}\u{62F} \u{62D}\u{645}\u{648}\u{62F}\u{647}", 'Email' => 'yousef120161119@gmail.com', 'ContactNumber' => '594131577'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $summary = $this->importRows(self::RECORDS);

        $this->command?->info(sprintf(
            'Field engineer ArcGIS seed completed: %d rows, %d updated, %d unchanged, %d skipped, %d missing users.',
            $summary['rows'],
            $summary['updated'],
            $summary['unchanged'],
            $summary['skipped'],
            $summary['missing_users'],
        ));

        if ($summary['email_conflicts'] > 0) {
            $this->command?->warn(sprintf('Skipped %d email updates because the email belongs to another user.', $summary['email_conflicts']));
        }

        if ($summary['id_no_conflicts'] > 0) {
            $this->command?->warn(sprintf('Skipped %d ID updates because the ID belongs to another user.', $summary['id_no_conflicts']));
        }
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @return array{rows: int, updated: int, unchanged: int, skipped: int, missing_users: int, email_conflicts: int, id_no_conflicts: int}
     */
    public function importRows(iterable $rows): array
    {
        $summary = [
            'rows' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'missing_users' => 0,
            'email_conflicts' => 0,
            'id_no_conflicts' => 0,
        ];

        foreach ($rows as $row) {
            $row = $this->normalizeRowKeys($row);

            if (! $this->hasAnyValue($row)) {
                continue;
            }

            $summary['rows']++;

            $idNo = $this->normalizeText($this->rowValue($row, ['ID']));
            $email = $this->normalizeEmail($this->rowValue($row, ['E-mail', 'Email']));

            if ($idNo === null && $email === null) {
                $summary['skipped']++;

                continue;
            }

            $user = $this->findUser($idNo, $email);

            if (! $user instanceof User) {
                $summary['missing_users']++;

                continue;
            }

            $updates = array_filter([
                'username_arcgis' => $this->normalizeText($this->rowValue($row, ['AssignedTo', 'Assigned To'])),
                'name_en' => $this->normalizeText($this->rowValue($row, ['Name English'])),
                'name' => $this->normalizeText($this->rowValue($row, ['Name Arabic'])),
                'phone' => $this->normalizePhone($this->rowValue($row, ['Contact Number'])),
            ], fn (?string $value): bool => $value !== null);

            if ($email !== null) {
                if ($this->belongsToAnotherUser('email', $email, $user)) {
                    $summary['email_conflicts']++;
                } else {
                    $updates['email'] = $email;
                }
            }

            if ($idNo !== null && $user->id_no !== $idNo) {
                if ($this->belongsToAnotherUser('id_no', $idNo, $user)) {
                    $summary['id_no_conflicts']++;
                } elseif (blank($user->id_no)) {
                    $updates['id_no'] = $idNo;
                }
            }

            $user->fill($updates);

            if (! $user->isDirty()) {
                $summary['unchanged']++;

                continue;
            }

            $user->save();
            $summary['updated']++;
        }

        return $summary;
    }

    private function findUser(?string $idNo, ?string $email): ?User
    {
        if ($idNo !== null) {
            $user = User::query()->where('id_no', $idNo)->first();

            if ($user instanceof User) {
                return $user;
            }
        }

        if ($email === null) {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function belongsToAnotherUser(string $column, string $value, User $user): bool
    {
        $query = User::query()
            ->where($column, $value)
            ->whereKeyNot($user->getKey());

        if ($column === 'email') {
            $query = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($value)])
                ->whereKeyNot($user->getKey());
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $headings
     */
    private function rowValue(array $row, array $headings): mixed
    {
        foreach ($headings as $heading) {
            $key = $this->normalizeHeading($heading);

            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeading($key)] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasAnyValue(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeText($value) !== null) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeading(mixed $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', (string) $value));
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $value = $this->normalizeText($value);

        return $value === null ? null : strtolower($value);
    }

    private function normalizePhone(mixed $value): ?string
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00970') && strlen($digits) === 14) {
            return '0'.substr($digits, 5);
        }

        if (str_starts_with($digits, '970') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '5') && strlen($digits) === 9) {
            return '0'.$digits;
        }

        return $digits;
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }

        $value = trim(str_replace("\u{00A0}", ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
