"""Extra HTTP cases, called by http_smoke.py using its temporary fixtures."""
import re, json, urllib.parse

def run_features(env):
    admin, reception, public = env['admin'], env['reception'], env['public']
    Page, check = env['Page'], env['check']
    doctor, prefix, date = env['doctor'], env['prefix'], env['date']
    bookpath, queue, printpath = env['bookpath'], env['queue'], env['printpath']
    basepath = urllib.parse.urlsplit(env['args'].url).path.rstrip('/')

    def switch(client, path, lang, draft=None):
        _, body, _, _ = client.get(path)
        data = {'csrf':Page(body).value('csrf'),'language':lang,'return':basepath+path}
        if draft is not None: data['booking_draft']=json.dumps(draft,ensure_ascii=False)
        return client.post('/language.php',data)

    def action(client, ident, command):
        _, body, _, _ = client.get('/admin/doctors.php')
        # Reception has no admin form; its own dashboard provides its session CSRF token.
        if 'name="csrf"' not in body: _,body,_,_=client.get('/reception/index.php')
        return client.post('/admin/doctors.php',{'csrf':Page(body).value('csrf'),'id':ident,'action':command})

    for command in ['delete','archive','restore']:
        check(action(reception,doctor,command)[0]==403,'Receptionist HTTP '+command+' forbidden')
    check('appointment history' in action(admin,doctor,'delete')[1],'Direct deletion request cannot remove a doctor with appointments')
    check('appointments still need attention' in action(admin,doctor,'archive')[1],'Future bookings block archive with a clear warning')

    _,body,_,_=admin.get('/admin/doctors.php')
    data={'csrf':Page(body).value('csrf'),'name':prefix+' Empty','specialization':'General Medicine','qualifications':'MBBS','phone':'03001234567','active':'1'}
    _,body,_,_=admin.post('/admin/doctors.php',data)
    row=next(row for row in re.findall(r'<tr>.*?</tr>',body,re.S) if prefix+' Empty' in row)
    empty=int(re.search(r'\?edit=(\d+)',row).group(1))
    check('data-confirm=' in row and 'Permanently delete' in row,'Unused doctor delete action includes a confirmation dialog message')
    check('Doctor permanently deleted.' in action(admin,empty,'delete')[1],'Admin can permanently delete unused doctor over HTTP')
    check(public.get('/book.php?doctor='+str(empty))[0]==404,'Deleted doctor is no longer bookable')

    # Cancel only this suite's remaining appointments, never hospital/demo records.
    _,body,_,_=reception.get(queue)
    ids=[i['value'] for i in Page(body).inputs if i.get('name')=='appointment_id']
    for ident in ids:
        _,body,_,_=reception.post(queue,{'csrf':Page(body).value('csrf'),'action':'status','appointment_id':ident,'status':'Cancelled'})
    check('Doctor archived.' in action(admin,doctor,'archive')[1],'Archiving succeeds after outstanding appointments are resolved')
    check(prefix+' Doctor' not in public.get('/doctors.php?q='+prefix)[1],'Archived doctor disappears from public directory')
    check(public.get(bookpath)[0]==404,'Archived doctor rejects public booking page')
    _,body,_,_=reception.get(printpath)
    check(re.findall(r'<strong>#(\d+)</strong>',body)==['1','2','3'],'Archive preserves every appointment and its printed token order')
    check(prefix+' Doctor' in reception.get('/reception/index.php?date='+date.isoformat())[1],'Archived doctor with history stays visible to authorized reception')
    check(env['stranger'].get(env['slipurl'])[0]==200,'Private historical slip remains available after archive')
    _,body,_,_=admin.get('/admin/doctors.php?edit='+str(doctor))
    edit={'csrf':Page(body).value('csrf'),'id':doctor,'name':prefix+' Doctor','specialization':'QA Medicine','qualifications':'MBBS','phone':'03001234567','active':'1'}
    admin.post('/admin/doctors.php',edit)
    check(public.get(bookpath)[0]==404,'Editing active checkbox cannot silently restore an archived doctor')
    check('Doctor restored' in action(admin,doctor,'restore')[1] and public.get(bookpath)[0]==200,'Explicit restore reopens existing doctor schedule')

    # Both language directions and patient draft continuity.
    _,body,_,_=public.get(bookpath)
    draft={'name':'Urdu Test Patient','phone':'03001234567','reason':'معائنے کی درخواست','date':date.isoformat(),'submission_key':Page(body).value('submission_key')}
    status,body,url,_=switch(public,bookpath,'ur',draft)
    check(status==200 and '<html lang="ur" dir="rtl">' in body,'English-to-Urdu switch renders Urdu and RTL')
    check('doctor='+str(doctor) in url and 'Urdu Test Patient' in body and 'معائنے کی درخواست' in body,'Language switch preserves doctor URL and patient form draft')
    check(Page(body).value('submission_key')==draft['submission_key'],'Language switch preserves the duplicate-submission key')
    check('bootstrap.rtl.min.css' in body and 'اپائنٹمنٹ' in body,'Urdu booking uses local RTL assets and translated labels')
    check('name="csrf"' in body and 'value="'+date.isoformat()+'" checked' in body,'Language change preserves date selection and valid session form')
    status,body,url,_=switch(public,bookpath,'en',draft)
    check('<html lang="en" dir="ltr">' in body and 'Urdu Test Patient' in body,'Urdu-to-English switch preserves the draft and restores LTR')
    _,body,_,_=switch(public,bookpath,'ur',draft)
    details=dict(draft,csrf=Page(body).value('csrf'))
    bad=dict(details,phone='123')
    _,bad_body,_,_=public.post(bookpath,bad)
    check('درست پاکستانی موبائل نمبر' in bad_body,'Server-side phone validation is translated into Urdu')
    status,slip,slipurl,_=public.post(bookpath,details)
    check(status==200 and '/appointment.php?token=' in slipurl and 'آپ کی اپائنٹمنٹ کی تصدیق ہوگئی ہے۔' in slip,'Patient completes a booking in Urdu')
    check('>04</strong>' in slip and 'Urdu Test Patient' in slip and '6:30 PM' in slip,'Urdu booking preserves patient name, time and stable next token')
    check('اپائنٹمنٹ کی پرچی' in slip and 'dir="ltr"' in slip and 'data-print' in slip,'Urdu appointment slip has translated print action and readable reference')
    for path in ['/','/doctors.php','/login.php',bookpath]:
        check('<html lang="ur" dir="rtl">' in public.get(path)[1],'Session language persists on '+path.split('?')[0])
    for path in ['/admin/index.php','/admin/doctors.php','/admin/schedules.php?doctor='+str(doctor),'/admin/receptionists.php']:
        _,body,_,_=switch(admin,path,'ur')
        check('<html lang="ur" dir="rtl">' in body and 'سپر ایڈمن' in body,'Urdu admin page renders: '+path.split('?')[0])
    _,body,_,_=switch(reception,queue,'ur')
    check('value="Arrived"' in body and '>مریض آگیا ہے</option>' in body,'Queue displays Urdu statuses while posting original English enum values')
    _,body,_,_=reception.get(printpath)
    check('<html lang="ur" dir="rtl">' in body and 'مریضوں کی قطار کی فہرست' in body,'Receptionist patient list renders in Urdu RTL')
    check(re.findall(r'<strong>#(\d+)</strong>',body)==['1','2','3','4'] and '<td dir="ltr">03001234567</td>' in body,'Urdu print view preserves token order and LTR phone numbers')
    check('Urdu Test Patient' in body and prefix+' Doctor' in body,'Doctor and patient names are never automatically translated')
    _,body,_,_=public.get('/')
    check(public.post('/language.php',{'csrf':Page(body).value('csrf'),'language':'xx','return':basepath+'/'})[0]==422,'Unsupported language is rejected')
    check(public.post('/language.php',{'language':'ur','return':basepath+'/'})[0]==419,'Language endpoint requires CSRF protection')
    _,body,_,_=public.get('/')
    status,body,url,_=public.post('/language.php',{'csrf':Page(body).value('csrf'),'language':'en','return':'https://example.com/'})
    check(url==env['args'].url.rstrip('/')+'/','Language redirect cannot leave the hospital site')
    # Restore English for the remaining original regression assertions.
    switch(admin,'/admin/receptionists.php','en')
    switch(reception,queue,'en')
