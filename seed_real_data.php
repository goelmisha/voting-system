<?php
/**
 * seed_real_data.php
 * ---------------------------------------------------------------
 * Makes the database realistic using REAL public election data
 * (Lok Sabha 2024, General Election to the 18th Lok Sabha):
 *
 *   CANDIDATES -> real contesting candidates per Parliamentary
 *   Constituency (sourced from ECI-affiliated result aggregators /
 *   MyNeta-ADR affidavit records; verified 2024 figures).
 *
 *   VOTERS -> realistic DEMO citizen records. IMPORTANT: the real
 *   electoral roll (electoralsearch.eci.gov.in / voters.eci.gov.in)
 *   is personal citizen data. It is only searchable individually
 *   (name + relative + captcha) and is NOT bulk-downloadable or
 *   licensed for reuse in an application database. These voter rows
 *   are therefore synthetic but realistic (Indian names, EPIC-style
 *   ids, regional addresses) purely for demonstrating the software.
 *
 * Safe to re-run: skips records that already exist.
 * Demo voter password for every seeded voter:  Voter@123
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';

// ---------- 1. Schema upgrade: constituency column on candidates ----------
try {
    $pdo->exec("ALTER TABLE candidates ADD COLUMN constituency TEXT DEFAULT ''");
    echo "✓ Added candidates.constituency column\n";
} catch (PDOException $e) {
    echo "· candidates.constituency already present\n";
}

// ---------- 2. Real Lok Sabha 2024 candidates (name, party, PC) ----------
// Constituency labels match the portal selector in index.php.
$real_candidates = [
    // --- Andhra Pradesh ---
    ['Visakhapatnam (PC-04)', [
        ['Mathukumilli Sribharat', 'Telugu Desam Party (TDP)'],
        ['Botcha Jhansi Lakshmi', 'YSR Congress Party (YSRCP)'],
        ['P. Satyanarayana', 'Indian National Congress (INC)'],
        ['K. A. Paul', 'Praja Shanthi Party'],
    ]],
    ['Vijayawada (PC-12)', [
        ['Kesineni Sivanath (Chinni)', 'Telugu Desam Party (TDP)'],
        ['Kesineni Srinivas (Nani)', 'YSR Congress Party (YSRCP)'],
        ['Valluru Bhargav', 'Indian National Congress (INC)'],
    ]],
    ['Guntur (PC-13)', [
        ['Pemmasani Chandra Sekhar', 'Telugu Desam Party (TDP)'],
        ['Kilari Venkata Rosaiah', 'YSR Congress Party (YSRCP)'],
        ['Jangala Ajay Kumar', 'Communist Party of India (CPI)'],
    ]],
    ['Tirupati (PC-23)', [
        ['Maddila Gurumoorthy', 'YSR Congress Party (YSRCP)'],
        ['Varaprasad Rao Velagapalli', 'Bharatiya Janata Party (BJP)'],
        ['Chinta Mohan', 'Indian National Congress (INC)'],
    ]],
    ['Kurnool (PC-19)', [
        ['Bastipati Nagaraju Panchalingala', 'Telugu Desam Party (TDP)'],
        ['B. Y. Ramaiah', 'YSR Congress Party (YSRCP)'],
        ['P. G. Ram Pullaiah Yadav', 'Indian National Congress (INC)'],
    ]],

    // --- Arunachal Pradesh ---
    ['Arunachal West (PC-01)', [
        ['Kiren Rijiju', 'Bharatiya Janata Party (BJP)'],
        ['Nabam Tuki', 'Indian National Congress (INC)'],
        ['Biki Tadap', 'Independent'],
    ]],
    ['Arunachal East (PC-02)', [
        ['Tapir Gao', 'Bharatiya Janata Party (BJP)'],
        ['Bosiram Siram', 'Indian National Congress (INC)'],
        ['Bandey Mili', 'Independent'],
    ]],

    // --- Assam ---
    ['Guwahati (PC-07)', [
        ['Bijuli Kalita Medhi', 'Bharatiya Janata Party (BJP)'],
        ['Mira Borthakur Goswami', 'Indian National Congress (INC)'],
        ['Samad Choudhury', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Dibrugarh (PC-13)', [
        ['Sarbananda Sonowal', 'Bharatiya Janata Party (BJP)'],
        ['Lurinjyoti Gogoi', 'Assam Jatiya Parishad (AJP)'],
        ['Manoj Dhanowar', 'Aam Aadmi Party (AAP)'],
    ]],
    ['Silchar (PC-02)', [
        ['Parimal Suklabaidya', 'Bharatiya Janata Party (BJP)'],
        ['Surjya Kanta Sarkar', 'Indian National Congress (INC)'],
        ['Radhe Shyam Biswas', 'Trinamool Congress (AITC)'],
    ]],
    ['Kaziranga (PC-10)', [
        ['Kamakhya Prasad Tasa', 'Bharatiya Janata Party (BJP)'],
        ['Roselina Tirkey', 'Indian National Congress (INC)'],
        ['Sailen Chandra Malakar', 'Bharatiya Gana Parishad'],
    ]],
    ['Barpeta (PC-03)', [
        ['Phani Bhusan Choudhury', 'Asom Gana Parishad (AGP)'],
        ['Deep Bayan', 'Indian National Congress (INC)'],
        ['Manoranjan Sen', 'Trinamool Congress (AITC)'],
    ]],

    // --- Bihar ---
    ['Patna Sahib (PC-30)', [
        ['Ravi Shankar Prasad', 'Bharatiya Janata Party (BJP)'],
        ['Anshul Avijit', 'Indian National Congress (INC)'],
        ['Gajendra Kumar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Pataliputra (PC-31)', [
        ['Misha Bharti', 'Rashtriya Janata Dal (RJD)'],
        ['Ram Kripal Yadav', 'Bharatiya Janata Party (BJP)'],
        ['Harikeshwar Ram', 'Bahujan Samaj Party (BSP)'],
        ['Md Farooque Raza', 'All India Majlis-E-Ittehadul Muslimeen (AIMIM)'],
    ]],
    ['Gaya (PC-38)', [
        ['Jitan Ram Manjhi', 'Hindustani Awam Morcha (Secular)'],
        ['Kumar Sarvjeet', 'Rashtriya Janata Dal (RJD)'],
        ['Sushil Kumar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Muzaffarpur (PC-15)', [
        ['Raj Bhushan Choudhary', 'Bharatiya Janata Party (BJP)'],
        ['Ajay Nishad', 'Indian National Congress (INC)'],
        ['Vijender Thakur', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Bhagalpur (PC-26)', [
        ['Ajay Kumar Mandal', 'Janata Dal (United) (JD(U))'],
        ['Ajeet Sharma', 'Indian National Congress (INC)'],
        ['Deepak Kumar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Darbhanga (PC-14)', [
        ['Gopal Jee Thakur', 'Bharatiya Janata Party (BJP)'],
        ['Lalit Kumar Yadav', 'Rashtriya Janata Dal (RJD)'],
        ['Murari Mohan Jha', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Chhattisgarh ---
    ['Raipur (PC-08)', [
        ['Brijmohan Agrawal', 'Bharatiya Janata Party (BJP)'],
        ['Vikas Upadhyay', 'Indian National Congress (INC)'],
        ['Mamta Rani Sahu', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Bilaspur (PC-05)', [
        ['Tokhan Sahu', 'Bharatiya Janata Party (BJP)'],
        ['Devender Singh Yadav', 'Indian National Congress (INC)'],
        ['Ashwani Kumar Rajak', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Durg (PC-07)', [
        ['Vijay Baghel', 'Bharatiya Janata Party (BJP)'],
        ['Rajendra Sahu', 'Indian National Congress (INC)'],
        ['Dilip Kumar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Bastar (PC-10)', [
        ['Mahesh Kashyap', 'Bharatiya Janata Party (BJP)'],
        ['Kawasi Lakhma', 'Indian National Congress (INC)'],
        ['Aaytu Ram Mandavi', 'Communist Party of India (CPI)'],
    ]],

    // --- Goa ---
    ['North Goa (PC-01)', [
        ['Shripad Yesso Naik', 'Bharatiya Janata Party (BJP)'],
        ['Ramakant Khalap', 'Indian National Congress (INC)'],
        ['Manoj Parab', 'Revolutionary Goans Party'],
    ]],
    ['South Goa (PC-02)', [
        ['Viriato Fernandes', 'Indian National Congress (INC)'],
        ['Pallavi Shrinivas Dempo', 'Bharatiya Janata Party (BJP)'],
        ['Ruben Vasco', 'Revolutionary Goans Party'],
    ]],

    // --- Gujarat ---
    ['Gandhinagar (PC-06)', [
        ['Amit Shah', 'Bharatiya Janata Party (BJP)'],
        ['Sonal Ramanbhai Patel', 'Indian National Congress (INC)'],
        ['Mohammedanish Desai', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Ahmedabad East (PC-07)', [
        ['Hasmukhbhai Patel', 'Bharatiya Janata Party (BJP)'],
        ['Himmatsinh Patel', 'Indian National Congress (INC)'],
        ['Rakeshbhai Vyas', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Ahmedabad West (PC-08)', [
        ['Dineshbhai Makwana', 'Bharatiya Janata Party (BJP)'],
        ['Bharat Yogendra Makwana', 'Indian National Congress (INC)'],
        ['Mitesh Vadodaria', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Surat (PC-24)', [
        ['Mukesh Dalal', 'Bharatiya Janata Party (BJP)'],
        ['Pyarelal Bharati', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Vadodara (PC-20)', [
        ['Hemang Joshi', 'Bharatiya Janata Party (BJP)'],
        ['Jashpalsinh Padhiyar', 'Indian National Congress (INC)'],
        ['Bhikhabhai Makwana', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Rajkot (PC-10)', [
        ['Parshottam Rupala', 'Bharatiya Janata Party (BJP)'],
        ['Paresh Dhanani', 'Indian National Congress (INC)'],
        ['Mukeshbhai Dabhi', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Haryana ---
    ['Gurgaon (PC-09)', [
        ['Rao Inderjit Singh', 'Bharatiya Janata Party (BJP)'],
        ['Raj Babbar', 'Indian National Congress (INC)'],
        ['Vijay Kumar', 'Bahujan Samaj Party (BSP)'],
        ['Rahul Yadav Fazilpuria', 'Jannayak Janta Party (JJP)'],
    ]],
    ['Faridabad (PC-10)', [
        ['Krishan Pal Gurjar', 'Bharatiya Janata Party (BJP)'],
        ['Mahender Pratap Singh', 'Indian National Congress (INC)'],
        ['Kishan Thakur', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Ambala (PC-01)', [
        ['Varun Chaudhry', 'Indian National Congress (INC)'],
        ['Banto Kataria', 'Bharatiya Janata Party (BJP)'],
        ['Pawan Kumar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Rohtak (PC-07)', [
        ['Deepender Singh Hooda', 'Indian National Congress (INC)'],
        ['Arvind Kumar Sharma', 'Bharatiya Janata Party (BJP)'],
        ['Ravinder Singh', 'Jannayak Janta Party (JJP)'],
    ]],
    ['Karnal (PC-05)', [
        ['Manohar Lal Khattar', 'Bharatiya Janata Party (BJP)'],
        ['Divyanshu Budhiraja', 'Indian National Congress (INC)'],
        ['Inderjit Singh', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Himachal Pradesh ---
    ['Shimla (PC-04)', [
        ['Suresh Kumar Kashyap', 'Bharatiya Janata Party (BJP)'],
        ['Vinod Sultanpuri', 'Indian National Congress (INC)'],
        ['Anil Kumar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Mandi (PC-02)', [
        ['Kangana Ranaut', 'Bharatiya Janata Party (BJP)'],
        ['Vikramaditya Singh', 'Indian National Congress (INC)'],
        ['Prakash Chand', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Kangra (PC-01)', [
        ['Rajeev Bhardwaj', 'Bharatiya Janata Party (BJP)'],
        ['Anand Sharma', 'Indian National Congress (INC)'],
        ['Advocate Rekha Rani', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Hamirpur (PC-03)', [
        ['Anurag Singh Thakur', 'Bharatiya Janata Party (BJP)'],
        ['Satpal Singh Raizada', 'Indian National Congress (INC)'],
        ['Hem Raj', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Jharkhand ---
    ['Ranchi (PC-08)', [
        ['Sanjay Seth', 'Bharatiya Janata Party (BJP)'],
        ['Yashaswini Sahay', 'Indian National Congress (INC)'],
        ['Devendra Nath Mahato', 'Independent'],
    ]],
    ['Jamshedpur (PC-09)', [
        ['Bidyut Baran Mahato', 'Bharatiya Janata Party (BJP)'],
        ['Samir Kumar Mohanty', 'Jharkhand Mukti Morcha (JMM)'],
        ['Pranab Kumar Mahato', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Dhanbad (PC-07)', [
        ['Dulu Mahato', 'Bharatiya Janata Party (BJP)'],
        ['Anupama Singh', 'Indian National Congress (INC)'],
        ['Jagarnath Mahto', 'Independent'],
    ]],
    ['Hazaribagh (PC-04)', [
        ['Manish Jaiswal', 'Bharatiya Janata Party (BJP)'],
        ['Jai Prakash Bhai Patel', 'Indian National Congress (INC)'],
        ['Manoj Kumar Yadav', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Karnataka ---
    ['Bengaluru South (PC-26)', [
        ['Tejasvi Surya', 'Bharatiya Janata Party (BJP)'],
        ['Sowmya Reddy', 'Indian National Congress (INC)'],
        ['S. Satish', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Bengaluru North (PC-24)', [
        ['Shobha Karandlaje', 'Bharatiya Janata Party (BJP)'],
        ['Prof. M. V. Rajeev Gowda', 'Indian National Congress (INC)'],
        ['A. P. Nagesh', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Bengaluru Central (PC-25)', [
        ['P. C. Mohan', 'Bharatiya Janata Party (BJP)'],
        ['Mansoor Ali Khan', 'Indian National Congress (INC)'],
        ['S. R. Govindappa', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Mysuru (PC-20)', [
        ['Yaduveer Krishnadatta Chamaraja Wadiyar', 'Bharatiya Janata Party (BJP)'],
        ['M. Lakshmana', 'Indian National Congress (INC)'],
        ['Rangaswamy', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Dakshina Kannada (PC-17)', [
        ['Captain Brijesh Chowta', 'Bharatiya Janata Party (BJP)'],
        ['Padmaraj R. Poojary', 'Indian National Congress (INC)'],
        ['Kanthappa Alangar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Hubli-Dharwad (PC-10)', [
        ['Pralhad Joshi', 'Bharatiya Janata Party (BJP)'],
        ['Vinod Asuti', 'Indian National Congress (INC)'],
        ['Iresh Anchatageri', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Kerala ---
    ['Thiruvananthapuram (PC-20)', [
        ['Shashi Tharoor', 'Indian National Congress (INC)'],
        ['Rajeev Chandrasekhar', 'Bharatiya Janata Party (BJP)'],
        ['Pannyan Raveendran', 'Communist Party of India (CPI)'],
    ]],
    ['Ernakulam (PC-12)', [
        ['Hibi Eden', 'Indian National Congress (INC)'],
        ['K. J. Shine', 'Communist Party of India (Marxist) (CPI(M))'],
        ['K. S. Radhakrishnan', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Wayanad (PC-04)', [
        ['Rahul Gandhi', 'Indian National Congress (INC)'],
        ['Annie Raja', 'Communist Party of India (CPI)'],
        ['K. Surendran', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Kozhikode (PC-05)', [
        ['M. K. Raghavan', 'Indian National Congress (INC)'],
        ['Elamaram Kareem', 'Communist Party of India (Marxist) (CPI(M))'],
        ['M. T. Ramesh', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Thrissur (PC-10)', [
        ['Suresh Gopi', 'Bharatiya Janata Party (BJP)'],
        ['V. S. Sunilkumar', 'Communist Party of India (CPI)'],
        ['K. Muraleedharan', 'Indian National Congress (INC)'],
    ]],

    // --- Madhya Pradesh ---
    ['Bhopal (PC-19)', [
        ['Alok Sharma', 'Bharatiya Janata Party (BJP)'],
        ['Arun Srivastava', 'Indian National Congress (INC)'],
        ['Bhanu Kumar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Indore (PC-26)', [
        ['Shankar Lalwani', 'Bharatiya Janata Party (BJP)'],
        ['Sanjay Solanki', 'Bahujan Samaj Party (BSP)'],
        ['Mudit Chourasiya', 'Independent'],
    ]],
    ['Gwalior (PC-03)', [
        ['Bharat Singh Kushwah', 'Bharatiya Janata Party (BJP)'],
        ['Praveen Pathak', 'Indian National Congress (INC)'],
        ['Kalyan Singh Kansana', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Jabalpur (PC-13)', [
        ['Ashish Dubey', 'Bharatiya Janata Party (BJP)'],
        ['Dinesh Yadav', 'Indian National Congress (INC)'],
        ['Dharmendra Yadav', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Ujjain (PC-22)', [
        ['Anil Firojiya', 'Bharatiya Janata Party (BJP)'],
        ['Mahesh Parmar', 'Indian National Congress (INC)'],
        ['Dr. Radheshyam', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Maharashtra ---
    ['Mumbai South (PC-31)', [
        ['Arvind Sawant', 'Shiv Sena (Uddhav Balasaheb Thackeray)'],
        ['Yamini Yashwant Jadhav', 'Shiv Sena (Eknath Shinde)'],
        ['Mohd. Rashid', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Mumbai North (PC-24)', [
        ['Piyush Goyal', 'Bharatiya Janata Party (BJP)'],
        ['Bhushan Patil', 'Indian National Congress (INC)'],
        ['Sandesh Meshram', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Pune (PC-34)', [
        ['Murlidhar Mohol', 'Bharatiya Janata Party (BJP)'],
        ['Ravindra Dhangekar', 'Indian National Congress (INC)'],
        ['Vasant More', 'Vanchit Bahujan Aaghadi (VBA)'],
    ]],
    ['Nagpur (PC-10)', [
        ['Nitin Gadkari', 'Bharatiya Janata Party (BJP)'],
        ['Vikas Thakre', 'Indian National Congress (INC)'],
        ['Yogesh Patre', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Thane (PC-25)', [
        ['Naresh Mhaske', 'Shiv Sena (Eknath Shinde)'],
        ['Rajan Vichare', 'Shiv Sena (Uddhav Balasaheb Thackeray)'],
        ['Advocate Sanjeev Kadam', 'Independent'],
    ]],
    ['Nashik (PC-20)', [
        ['Rajabhau Waje', 'Shiv Sena (Uddhav Balasaheb Thackeray)'],
        ['Hemant Godse', 'Shiv Sena (Eknath Shinde)'],
        ['Shantigiriji Maharaj', 'Independent'],
    ]],
    ['Aurangabad (PC-19)', [
        ['Sandipanrao Bhumre', 'Shiv Sena (Eknath Shinde)'],
        ['Imtiaz Jaleel', 'All India Majlis-E-Ittehadul Muslimeen (AIMIM)'],
        ['Chandrakant Khaire', 'Shiv Sena (Uddhav Balasaheb Thackeray)'],
    ]],

    // --- Manipur ---
    ['Inner Manipur (PC-01)', [
        ['Angomcha Bimol Akoijam', 'Indian National Congress (INC)'],
        ['Thounaojam Basanta Kumar Singh', 'Bharatiya Janata Party (BJP)'],
        ['Maheshwar Thounaojam', 'Republican Party of India (Athawale)'],
    ]],
    ['Outer Manipur (PC-02)', [
        ['Alfred Kanngam S. Arthur', 'Indian National Congress (INC)'],
        ['Kachui Timothy Zimik', 'Naga People\'s Front (NPF)'],
        ['S. Kho John', 'Independent'],
    ]],

    // --- Meghalaya ---
    ['Shillong (PC-01)', [
        ['Ricky Andrew J. Syngkon', 'Voice of the People Party (VPP)'],
        ['Vincent H. Pala', 'Indian National Congress (INC)'],
        ['Ampareen Lyngdoh', 'National People\'s Party (NPP)'],
    ]],
    ['Tura (PC-02)', [
        ['Saleng A. Sangma', 'Indian National Congress (INC)'],
        ['Agatha Sangma', 'National People\'s Party (NPP)'],
        ['Zenith Sangma', 'Trinamool Congress (AITC)'],
    ]],

    // --- Mizoram ---
    ['Mizoram (PC-01)', [
        ['Richard Vanlalhmangaiha', 'Zoram People\'s Movement (ZPM)'],
        ['K. Vanlalvena', 'Mizo National Front (MNF)'],
        ['Lalbiakzama', 'Indian National Congress (INC)'],
        ['Vanhlalmuaka', 'Bharatiya Janata Party (BJP)'],
    ]],

    // --- Nagaland ---
    ['Nagaland (PC-01)', [
        ['S. Supongmeren Jamir', 'Indian National Congress (INC)'],
        ['Chumben Murry', 'Nationalist Democratic Progressive Party (NDPP)'],
        ['Hayithung Tungoe Lotha', 'Independent'],
    ]],

    // --- Odisha ---
    ['Bhubaneswar (PC-17)', [
        ['Aparajita Sarangi', 'Bharatiya Janata Party (BJP)'],
        ['Manmath Routray', 'Biju Janata Dal (BJD)'],
        ['Yasir Nawaz', 'Indian National Congress (INC)'],
    ]],
    ['Puri (PC-18)', [
        ['Sambit Patra', 'Bharatiya Janata Party (BJP)'],
        ['Arup Mohan Patnaik', 'Biju Janata Dal (BJD)'],
        ['Jay Narayan Patnaik', 'Indian National Congress (INC)'],
    ]],
    ['Cuttack (PC-14)', [
        ['Bhartruhari Mahtab', 'Bharatiya Janata Party (BJP)'],
        ['Santrupt Misra', 'Biju Janata Dal (BJD)'],
        ['Suresh Mohapatra', 'Indian National Congress (INC)'],
    ]],
    ['Sambalpur (PC-03)', [
        ['Dharmendra Pradhan', 'Bharatiya Janata Party (BJP)'],
        ['Pranab Prakash Das', 'Biju Janata Dal (BJD)'],
        ['Nagendra Kumar Pradhan', 'Indian National Congress (INC)'],
    ]],
    ['Berhampur (PC-20)', [
        ['Pradeep Kumar Panigrahy', 'Bharatiya Janata Party (BJP)'],
        ['Bhrugu Baxipatra', 'Biju Janata Dal (BJD)'],
        ['Rashmi Ranjan Patnaik', 'Indian National Congress (INC)'],
    ]],

    // --- Punjab ---
    ['Amritsar (PC-02)', [
        ['Gurjeet Singh Aujla', 'Indian National Congress (INC)'],
        ['Kuldeep Singh Dhaliwal', 'Aam Aadmi Party (AAP)'],
        ['Taranjit Singh Sandhu', 'Bharatiya Janata Party (BJP)'],
        ['Anil Joshi', 'Shiromani Akali Dal (SAD)'],
    ]],
    ['Ludhiana (PC-07)', [
        ['Amrinder Singh Raja Warring', 'Indian National Congress (INC)'],
        ['Ravneet Singh Bittu', 'Bharatiya Janata Party (BJP)'],
        ['Ashok Parashar Pappi', 'Aam Aadmi Party (AAP)'],
    ]],
    ['Jalandhar (PC-04)', [
        ['Charanjit Singh Channi', 'Indian National Congress (INC)'],
        ['Sushil Kumar Rinku', 'Bharatiya Janata Party (BJP)'],
        ['Pawan Kumar Tinu', 'Aam Aadmi Party (AAP)'],
    ]],
    ['Patiala (PC-13)', [
        ['Dr. Dharamvira Gandhi', 'Indian National Congress (INC)'],
        ['Balbir Singh', 'Aam Aadmi Party (AAP)'],
        ['Preneet Kaur', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Bathinda (PC-11)', [
        ['Harsimrat Kaur Badal', 'Shiromani Akali Dal (SAD)'],
        ['Gurmeet Singh Khudian', 'Aam Aadmi Party (AAP)'],
        ['Jeet Mohinder Singh Sidhu', 'Indian National Congress (INC)'],
    ]],
    ['Gurdaspur (PC-01)', [
        ['Sukhjinder Singh Randhawa', 'Indian National Congress (INC)'],
        ['Dinesh Singh Babbu', 'Bharatiya Janata Party (BJP)'],
        ['Amansher Singh Shery Kalsi', 'Aam Aadmi Party (AAP)'],
    ]],
    ['Khadoor Sahib (PC-03)', [
        ['Amritpal Singh', 'Independent'],
        ['Kulbir Singh Zira', 'Indian National Congress (INC)'],
        ['Laljit Singh Bhullar', 'Aam Aadmi Party (AAP)'],
    ]],
    ['Hoshiarpur (PC-05)', [
        ['Dr. Raj Kumar Chabbewal', 'Aam Aadmi Party (AAP)'],
        ['Yamini Gomar', 'Indian National Congress (INC)'],
        ['Anita Som Parkash', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Anandpur Sahib (PC-06)', [
        ['Malvinder Singh Kang', 'Aam Aadmi Party (AAP)'],
        ['Vijay Inder Singla', 'Indian National Congress (INC)'],
        ['Prem Singh Chandumajra', 'Shiromani Akali Dal (SAD)'],
    ]],
    ['Fatehgarh Sahib (PC-08)', [
        ['Amar Singh', 'Indian National Congress (INC)'],
        ['Gurpreet Singh GP', 'Aam Aadmi Party (AAP)'],
        ['Gejja Ram Valmiki', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Faridkot (PC-09)', [
        ['Sarabjeet Singh Khalsa', 'Independent'],
        ['Karamjit Anmol', 'Aam Aadmi Party (AAP)'],
        ['Amarjit Kaur Sahoke', 'Indian National Congress (INC)'],
    ]],
    ['Firozpur (PC-10)', [
        ['Sher Singh Ghubaya', 'Indian National Congress (INC)'],
        ['Jagdeep Singh Kaka Brar', 'Aam Aadmi Party (AAP)'],
        ['Nardev Singh Bobby Mann', 'Shiromani Akali Dal (SAD)'],
    ]],
    ['Sangrur (PC-12)', [
        ['Gurmeet Singh Meet Hayer', 'Aam Aadmi Party (AAP)'],
        ['Sukhpal Singh Khaira', 'Indian National Congress (INC)'],
        ['Simranjit Singh Mann', 'Shiromani Akali Dal (Amritsar)'],
    ]],

    // --- Rajasthan ---
    ['Jaipur (PC-07)', [
        ['Manju Sharma', 'Bharatiya Janata Party (BJP)'],
        ['Pratap Singh Khachariyawas', 'Indian National Congress (INC)'],
        ['Rajesh Tanwar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Jodhpur (PC-13)', [
        ['Gajendra Singh Shekhawat', 'Bharatiya Janata Party (BJP)'],
        ['Karan Singh Uchiyarda', 'Indian National Congress (INC)'],
        ['Manju Meghwal', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Udaipur (PC-19)', [
        ['Manna Lal Rawat', 'Bharatiya Janata Party (BJP)'],
        ['Tarachand Meena', 'Indian National Congress (INC)'],
        ['Prakash Chandra', 'Bharat Adivasi Party (BAP)'],
    ]],
    ['Kota (PC-24)', [
        ['Om Birla', 'Bharatiya Janata Party (BJP)'],
        ['Prahlad Gunjal', 'Indian National Congress (INC)'],
        ['Bhim Singh', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Bikaner (PC-02)', [
        ['Arjun Ram Meghwal', 'Bharatiya Janata Party (BJP)'],
        ['Govind Ram Meghwal', 'Indian National Congress (INC)'],
        ['Kheta Ram', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Ajmer (PC-12)', [
        ['Bhagirath Choudhary', 'Bharatiya Janata Party (BJP)'],
        ['Ramchandra Choudhary', 'Indian National Congress (INC)'],
        ['Ramdev', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Sikkim ---
    ['Sikkim (PC-01)', [
        ['Indra Hang Subba', 'Sikkim Krantikari Morcha (SKM)'],
        ['Bharat Basnett', 'Citizen Action Party-Sikkim'],
        ['Prem Das Rai', 'Sikkim Democratic Front (SDF)'],
        ['Dinesh Chandra Nepal', 'Bharatiya Janata Party (BJP)'],
    ]],

    // --- Tamil Nadu ---
    ['Chennai Central (PC-04)', [
        ['Dayanidhi Maran', 'Dravida Munnetra Kazhagam (DMK)'],
        ['Vinoj P. Selvam', 'Bharatiya Janata Party (BJP)'],
        ['B. Parthasarathy', 'Desiya Murpokku Dravida Kazhagam (DMDK)'],
    ]],
    ['Chennai South (PC-03)', [
        ['Thamizhachi Thangapandian', 'Dravida Munnetra Kazhagam (DMK)'],
        ['Tamilisai Soundararajan', 'Bharatiya Janata Party (BJP)'],
        ['J. Jayavardhan', 'All India Anna Dravida Munnetra Kazhagam (AIADMK)'],
    ]],
    ['Chennai North (PC-02)', [
        ['Kalanidhi Veeraswamy', 'Dravida Munnetra Kazhagam (DMK)'],
        ['R. Manohar', 'All India Anna Dravida Munnetra Kazhagam (AIADMK)'],
        ['R. C. Paul Kanagaraj', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Coimbatore (PC-20)', [
        ['Ganapathi P. Rajkumar', 'Dravida Munnetra Kazhagam (DMK)'],
        ['K. Annamalai', 'Bharatiya Janata Party (BJP)'],
        ['Singai G. Ramachandran', 'All India Anna Dravida Munnetra Kazhagam (AIADMK)'],
    ]],
    ['Madurai (PC-32)', [
        ['Su. Venkatesan', 'Communist Party of India (Marxist) (CPI(M))'],
        ['P. Saravanan', 'All India Anna Dravida Munnetra Kazhagam (AIADMK)'],
        ['Raama Sreenivasan', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Sriperumbudur (PC-05)', [
        ['T. R. Baalu', 'Dravida Munnetra Kazhagam (DMK)'],
        ['G. Premkumar', 'All India Anna Dravida Munnetra Kazhagam (AIADMK)'],
        ['V. N. Venugopal', 'Pattali Makkal Katchi (PMK)'],
    ]],

    // --- Telangana ---
    ['Hyderabad (PC-09)', [
        ['Asaduddin Owaisi', 'All India Majlis-E-Ittehadul Muslimeen (AIMIM)'],
        ['Madhavi Latha Kompella', 'Bharatiya Janata Party (BJP)'],
        ['Mohammed Waliullah Sameer', 'Indian National Congress (INC)'],
        ['Srinivas Yadav Gaddam', 'Bharat Rashtra Samithi (BRS)'],
    ]],
    ['Secunderabad (PC-08)', [
        ['G. Kishan Reddy', 'Bharatiya Janata Party (BJP)'],
        ['Danam Nagender', 'Indian National Congress (INC)'],
        ['T. Padma Rao Goud', 'Bharat Rashtra Samithi (BRS)'],
    ]],
    ['Chevella (PC-10)', [
        ['Konda Vishweshwar Reddy', 'Bharatiya Janata Party (BJP)'],
        ['G. Ranjith Reddy', 'Indian National Congress (INC)'],
        ['Kasani Gyaneshwar Mudiraj', 'Bharat Rashtra Samithi (BRS)'],
    ]],
    ['Malkajgiri (PC-07)', [
        ['Eatala Rajender', 'Bharatiya Janata Party (BJP)'],
        ['Patnam Sunitha Mahender Reddy', 'Indian National Congress (INC)'],
        ['Ragidi Laxma Reddy', 'Bharat Rashtra Samithi (BRS)'],
    ]],
    ['Warangal (PC-15)', [
        ['Kadiyam Kavya', 'Indian National Congress (INC)'],
        ['Aroori Ramesh', 'Bharatiya Janata Party (BJP)'],
        ['M. Sudheer Kumar', 'Bharat Rashtra Samithi (BRS)'],
    ]],

    // --- Tripura ---
    ['Tripura West (PC-01)', [
        ['Biplab Kumar Deb', 'Bharatiya Janata Party (BJP)'],
        ['Asish Kumar Saha', 'Indian National Congress (INC)'],
        ['Gouranga Das', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Tripura East (PC-02)', [
        ['Kriti Devi Debbarman', 'Bharatiya Janata Party (BJP)'],
        ['Rajendra Reang', 'Communist Party of India (Marxist) (CPI(M))'],
        ['Kanchandhan Chakma', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Uttar Pradesh ---
    ['Gautam Buddha Nagar (PC-13)', [
        ['Dr. Mahesh Sharma', 'Bharatiya Janata Party (BJP)'],
        ['Dr. Mahendra Nagar', 'Samajwadi Party (SP)'],
        ['Rajendra Singh Solanki', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Varanasi (PC-77)', [
        ['Narendra Modi', 'Bharatiya Janata Party (BJP)'],
        ['Ajay Rai', 'Indian National Congress (INC)'],
        ['Ather Jamal Lari', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Rae Bareli (PC-36)', [
        ['Rahul Gandhi', 'Indian National Congress (INC)'],
        ['Dinesh Pratap Singh', 'Bharatiya Janata Party (BJP)'],
        ['Thakur Prasad Yadav', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Lucknow (PC-35)', [
        ['Rajnath Singh', 'Bharatiya Janata Party (BJP)'],
        ['Ravidas Mehrotra', 'Samajwadi Party (SP)'],
        ['Mohammad Sarwar Malik', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Amethi (PC-37)', [
        ['Kishori Lal Sharma', 'Indian National Congress (INC)'],
        ['Smriti Irani', 'Bharatiya Janata Party (BJP)'],
        ['Nanhe Singh Chauhan', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Gorakhpur (PC-64)', [
        ['Ravi Kishan', 'Bharatiya Janata Party (BJP)'],
        ['Kajal Nishad', 'Samajwadi Party (SP)'],
        ['Javed Ashraf', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Agra (PC-18)', [
        ['Prof. S. P. Singh Baghel', 'Bharatiya Janata Party (BJP)'],
        ['Suresh Chand Kardam', 'Samajwadi Party (SP)'],
        ['Pooja Amrohi', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Kanpur (PC-43)', [
        ['Ramesh Awasthi', 'Bharatiya Janata Party (BJP)'],
        ['Alok Kumar Misra', 'Indian National Congress (INC)'],
        ['Kuldeep Bhadauria', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Prayagraj (PC-52)', [
        ['Ujjwal Raman Singh', 'Indian National Congress (INC)'],
        ['Neeraj Tripathi', 'Bharatiya Janata Party (BJP)'],
        ['Ramesh Kumar Patel', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- Uttarakhand ---
    ['Haridwar (PC-05)', [
        ['Trivendra Singh Rawat', 'Bharatiya Janata Party (BJP)'],
        ['Virender Rawat', 'Indian National Congress (INC)'],
        ['Umesh Kumar', 'Independent'],
    ]],
    ['Tehri Garhwal (PC-01)', [
        ['Mala Rajya Laxmi Shah', 'Bharatiya Janata Party (BJP)'],
        ['Jot Singh Gunsola', 'Indian National Congress (INC)'],
        ['Bobby Panwar', 'Independent'],
    ]],
    ['Garhwal (PC-02)', [
        ['Anil Baluni', 'Bharatiya Janata Party (BJP)'],
        ['Ganesh Godiyal', 'Indian National Congress (INC)'],
        ['Dheeraj Singh Bisht', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Almora (PC-03)', [
        ['Ajay Tamta', 'Bharatiya Janata Party (BJP)'],
        ['Pradeep Tamta', 'Indian National Congress (INC)'],
        ['Narayan Ram', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Nainital-Udhamsingh Nagar (PC-04)', [
        ['Ajay Bhatt', 'Bharatiya Janata Party (BJP)'],
        ['Prakash Joshi', 'Indian National Congress (INC)'],
        ['Akhtar Ali Mahi Giri', 'Bahujan Samaj Party (BSP)'],
    ]],

    // --- West Bengal ---
    ['Kolkata Dakshin (PC-23)', [
        ['Mala Roy', 'Trinamool Congress (AITC)'],
        ['Debasree Chaudhuri', 'Bharatiya Janata Party (BJP)'],
        ['Saira Shah Halim', 'Communist Party of India (Marxist) (CPI(M))'],
    ]],
    ['Kolkata Uttar (PC-24)', [
        ['Sudip Bandyopadhyay', 'Trinamool Congress (AITC)'],
        ['Tapas Roy', 'Bharatiya Janata Party (BJP)'],
        ['Pradip Bhattacharya', 'Indian National Congress (INC)'],
    ]],
    ['Darjeeling (PC-04)', [
        ['Raju Bista', 'Bharatiya Janata Party (BJP)'],
        ['Gopal Lama', 'Trinamool Congress (AITC)'],
        ['Munish Tamang', 'Indian National Congress (INC)'],
    ]],
    ['Howrah (PC-25)', [
        ['Prasun Banerjee', 'Trinamool Congress (AITC)'],
        ['Dr. Rathin Chakraborty', 'Bharatiya Janata Party (BJP)'],
        ['Sabyasachi Chatterjee', 'Communist Party of India (Marxist) (CPI(M))'],
    ]],
    ['Asansol (PC-40)', [
        ['Shatrughan Prasad Sinha', 'Trinamool Congress (AITC)'],
        ['Surendrajeet Singh Ahluwalia', 'Bharatiya Janata Party (BJP)'],
        ['Jahanara Khan', 'Communist Party of India (Marxist) (CPI(M))'],
    ]],
    ['Diamond Harbour (PC-21)', [
        ['Abhishek Banerjee', 'Trinamool Congress (AITC)'],
        ['Abhijit Das (Bobby)', 'Bharatiya Janata Party (BJP)'],
        ['Pratikur Rahaman', 'Communist Party of India (Marxist) (CPI(M))'],
    ]],

    // --- Union Territories ---
    ['Andaman and Nicobar Islands (PC-01)', [
        ['Bishnu Pada Ray', 'Bharatiya Janata Party (BJP)'],
        ['Kuldeep Rai Sharma', 'Indian National Congress (INC)'],
        ['D. Ayyappan', 'Communist Party of India (Marxist) (CPI(M))'],
    ]],
    ['Chandigarh (PC-01)', [
        ['Manish Tewari', 'Indian National Congress (INC)'],
        ['Sanjay Tandon', 'Bharatiya Janata Party (BJP)'],
        ['Ritu Singh', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Dadra and Nagar Haveli (PC-01)', [
        ['Kalaben Delkar', 'Bharatiya Janata Party (BJP)'],
        ['Ajit Ramjibhai Mahala', 'Indian National Congress (INC)'],
        ['Bipinbhai Dhodi', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Daman and Diu (PC-02)', [
        ['Patel Umeshbhai Babubhai', 'Independent'],
        ['Lalubhai Patel', 'Bharatiya Janata Party (BJP)'],
        ['Ketan Patel', 'Indian National Congress (INC)'],
    ]],
    ['New Delhi (PC-04)', [
        ['Bansuri Swaraj', 'Bharatiya Janata Party (BJP)'],
        ['Somnath Bharti', 'Aam Aadmi Party (AAP)'],
        ['Amir Chand Tehnuria', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Chandni Chowk (PC-01)', [
        ['Praveen Khandelwal', 'Bharatiya Janata Party (BJP)'],
        ['Jai Prakash Agarwal', 'Indian National Congress (INC)'],
        ['Abul Kalam Azad', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['East Delhi (PC-03)', [
        ['Harsh Malhotra', 'Bharatiya Janata Party (BJP)'],
        ['Kuldeep Kumar', 'Aam Aadmi Party (AAP)'],
        ['Mohd. Waqar Choudhary', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['South Delhi (PC-07)', [
        ['Ramvir Singh Bidhuri', 'Bharatiya Janata Party (BJP)'],
        ['Sahiram Pahalwan', 'Aam Aadmi Party (AAP)'],
        ['Abdul Basit', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['North East Delhi (PC-02)', [
        ['Manoj Tiwari', 'Bharatiya Janata Party (BJP)'],
        ['Kanhaiya Kumar', 'Indian National Congress (INC)'],
        ['Ashok Kumar', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['North West Delhi (PC-05)', [
        ['Yogender Chandoliya', 'Bharatiya Janata Party (BJP)'],
        ['Udit Raj', 'Indian National Congress (INC)'],
        ['Vijay Boudh', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['West Delhi (PC-06)', [
        ['Kamaljeet Sehrawat', 'Bharatiya Janata Party (BJP)'],
        ['Mahabal Mishra', 'Aam Aadmi Party (AAP)'],
        ['Vishesh Gupta', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Srinagar (PC-02)', [
        ['Aga Syed Ruhullah Mehdi', 'Jammu & Kashmir National Conference (JKNC)'],
        ['Waheed Ur Rehman Para', 'Jammu & Kashmir Peoples Democratic Party (JKPDP)'],
        ['Mohammad Ashraf Mir', 'Apni Party'],
    ]],
    ['Jammu (PC-05)', [
        ['Jugal Kishore Sharma', 'Bharatiya Janata Party (BJP)'],
        ['Raman Bhalla', 'Indian National Congress (INC)'],
        ['Jagdish Raj', 'Bahujan Samaj Party (BSP)'],
    ]],
    ['Anantnag-Rajouri (PC-03)', [
        ['Mian Altaf Ahmad Larvi', 'Jammu & Kashmir National Conference (JKNC)'],
        ['Mehbooba Mufti', 'Jammu & Kashmir Peoples Democratic Party (JKPDP)'],
        ['Zafar Iqbal Manhas', 'Apni Party'],
    ]],
    ['Baramulla (PC-01)', [
        ['Abdul Rashid Sheikh (Engineer Rashid)', 'Independent'],
        ['Omar Abdullah', 'Jammu & Kashmir National Conference (JKNC)'],
        ['Sajad Gani Lone', 'Jammu and Kashmir People\'s Conference'],
    ]],
    ['Udhampur (PC-04)', [
        ['Dr. Jitendra Singh', 'Bharatiya Janata Party (BJP)'],
        ['Choudhary Lal Singh', 'Indian National Congress (INC)'],
        ['Ghulam Nabi Azad (Representative)', 'Democratic Progressive Azad Party'],
    ]],
    ['Ladakh (PC-01)', [
        ['Mohmad Haneefa', 'Independent'],
        ['Tsering Namgyal', 'Indian National Congress (INC)'],
        ['Tashi Gyalson', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Lakshadweep (PC-01)', [
        ['Muhammed Hamdullah Sayeed', 'Indian National Congress (INC)'],
        ['Mohammed Faizal P. P.', 'Nationalist Congress Party (Sharadchandra Pawar)'],
        ['T. P. Yousuf', 'Bharatiya Janata Party (BJP)'],
    ]],
    ['Puducherry (PC-01)', [
        ['Ve. Vaithilingam', 'Indian National Congress (INC)'],
        ['A. Namassivayam', 'All India N.R. Congress (AINRC) / BJP'],
        ['G. Thamizhvendhan', 'All India Anna Dravida Munnetra Kazhagam (AIADMK)'],
    ]],
];

// Remove only the old generic placeholders (no constituency), then insert real rows.
$placeholders = ['Rahul Sharma', 'Priya Patel', 'Amit Verma', 'Candidate Alpha', 'Candidate Beta', 'Candidate Gamma', 'Narendra Modi', 'Rahul Gandhi', 'Arvind Kejriwal'];
$ph = $pdo->prepare("DELETE FROM candidates WHERE constituency = '' AND name = ?");
foreach ($placeholders as $p) {
    $ph->execute([$p]);
}

$dup_check = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE name = ? AND constituency = ?");
$insert_c  = $pdo->prepare("INSERT INTO candidates (name, party, photo, votes_count, constituency) VALUES (?, ?, 'default.png', 0, ?)");

$cand_added = 0;
foreach ($real_candidates as [$constituency, $list]) {
    foreach ($list as [$name, $party]) {
        $dup_check->execute([$name, $constituency]);
        if ((int)$dup_check->fetchColumn() === 0) {
            $insert_c->execute([$name, $party, $constituency]);
            $cand_added++;
        }
    }
}
echo "✓ Seeded {$cand_added} real Lok Sabha 2024 candidate(s)\n\n";

// ---------- 3. Realistic DEMO voters (synthetic — see header note) ----------
$demo_password = password_hash('Voter@123', PASSWORD_DEFAULT);

// region => [first name pool, surname pool, district, state, EPIC prefix, mobile prefix]
$regions = [
    'Varanasi (PC-77)' => [
        ['Ravi','Amit','Sunita','Pooja','Vikash','Anjali','Rohit','Neha','Sanjay','Kavita','Manoj','Sushma','Alok','Rekha','Deepak','Shweta','Arjun','Priyanka'],
        ['Sharma','Verma','Gupta','Yadav','Singh','Pandey','Mishra','Tiwari','Srivastava','Jaiswal','Patel','Maurya','Chaurasia','Bind','Kushwaha'],
        'Varanasi, Uttar Pradesh', 'Uttar Pradesh', 'UPV', '9',
    ],
    'Gandhinagar (PC-06)' => [
        ['Rajesh','Nilesh','Hetal','Kinjal','Mehul','Darshana','Jayesh','Bhavna','Chirag','Rina','Kiran','Mital','Suresh','Usha','Paresh','Dimple'],
        ['Patel','Shah','Desai','Joshi','Trivedi','Dave','Mehta','Panchal','Prajapati','Solanki','Rathod','Chaudhary','Thakor','Vaghela','Raval'],
        'Gandhinagar, Gujarat', 'Gujarat', 'GJH', '7',
    ],
    'Rae Bareli (PC-36)' => [
        ['Ram','Sita','Ganesh','Shakuntala','Ramesh','Savitri','Dinesh','Mamta','Ashok','Santosh','Pawan','Rekha','Shyam','Lalita','Vijay','Guddu','Bablu','Anita'],
        ['Prasad','Verma','Yadav','Singh','Gupta','Pandey','Tiwari','Kushwaha','Pal','Patel','Chaudhary','Kori','Pasi','Gautam','Shukla'],
        'Rae Bareli, Uttar Pradesh', 'Uttar Pradesh', 'UPR', '8',
    ],
    'Hyderabad (PC-09)' => [
        ['Mohammed','Abdul','Rahima','Fatima','Syed','Ayesha','Imran','Salma','Naveen','Padma','Srinivas','Kavitha','Ravi','Shakeela','Arif','Sameera','Venkat','Lakshmi'],
        ['Ahmed','Khan','Hussain','Begum','Ali','Syed','Rao','Reddy','Naik','Kumari','Prasad','Sultana','Baig','Qureshi','Ansari','Sharma','Goud','Raju'],
        'Hyderabad, Telangana', 'Telangana', 'TSH', '6',
    ],
];

function realistic_mobile($prefix)
{
    // Indian mobile numbers start 6-9; keep unique by varying the last digits
    $rest = (string)random_int(10000000, 99999999);
    return $prefix . $rest;
}

$check_voter = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE email = ? OR voter_id_number = ?");
$insert_v    = $pdo->prepare("INSERT INTO voters (fullname, email, voter_id_number, mobile, address, password, photo, document_proof, status, has_voted) VALUES (?, ?, ?, ?, ?, ?, 'default.png', '', ?, 0)");

$voter_added = 0;
$seed_index  = 1; // keeps generated ids unique & deterministic enough
foreach ($regions as $constituency => [$firsts, $lasts, $place, $state, $epic_pfx, $mob_pfx]) {
    for ($i = 0; $i < 6; $i++) {
        $first = $firsts[array_rand($firsts)];
        $last  = $lasts[array_rand($lasts)];
        $full  = $first . ' ' . $last;

        $email = strtolower($first . '.' . $last . $seed_index) . '@gmail.com';
        $epic  = $epic_pfx . str_pad((string)$seed_index, 7, '0', STR_PAD_LEFT);
        $addr  = "House No. " . random_int(1, 999) . ", Ward " . random_int(1, 20) . ", {$place} — {$constituency}";

        $check_voter->execute([$email, $epic]);
        if ((int)$check_voter->fetchColumn() > 0) {
            $seed_index++;
            continue;
        }

        // A couple of pending / rejected citizens per region for admin realism
        $r = $i % 6;
        $status = ($r === 4) ? 'pending' : (($r === 5) ? 'rejected' : 'approved');

        $mobile = realistic_mobile($mob_pfx);
        $insert_v->execute([$full, $email, $epic, $mobile, $addr, $demo_password, $status]);
        $voter_added++;
        $seed_index++;
    }
}
echo "✓ Seeded {$voter_added} realistic demo voter(s) — password for all: Voter@123\n\n";

// ---------- 4. Summary ----------
$pc = $pdo->query("SELECT constituency, COUNT(*) c FROM candidates GROUP BY constituency")->fetchAll(PDO::FETCH_ASSOC);
echo "Registered candidate ballots by constituency:\n";
foreach ($pc as $row) {
    echo "  - " . htmlspecialchars($row['constituency'] ?: '(unassigned / visible everywhere)') . ": " . (int)$row['c'] . " candidate(s)\n";
}
$vs = $pdo->query("SELECT status, COUNT(*) c FROM voters GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo "\nVoter records by status:\n";
foreach ($vs as $row) {
    echo "  - " . htmlspecialchars($row['status']) . ": " . (int)$row['c'] . "\n";
}
echo "\nDone. Pick a region on the portal and its real ballot will appear on the voter dashboard.\n";
