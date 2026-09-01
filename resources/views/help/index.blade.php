@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
  <div class="col-lg-9">

    <h1 class="mb-1">Help &amp; Guide</h1>
    <p class="text-muted">A step-by-step guide to marking attendance. You can do everything from your phone — tap a name to mark a student, then Submit. Nothing here can break: if you make a mistake you can always change it and save again.</p>

    {{-- Quick jump links --}}
    <div class="card bg-body-tertiary mb-4">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">On this page</h2>
        <ol class="mb-0">
          <li><a href="#login">Logging in</a></li>
          <li><a href="#mark">Marking attendance</a></li>
          <li><a href="#books">Handing out books</a></li>
          <li><a href="#edit">Fixing or back-filling attendance</a></li>
          <li><a href="#todays-report">Today's Report</a></li>
          <li><a href="#full-year-report">Full Year Report</a></li>
          <li><a href="#sync">Registration Sync</a></li>
          <li><a href="#teachers">Teachers — adding &amp; removing (administrators)</a></li>
          <li><a href="#roles">Roles &amp; permissions (administrators)</a></li>
        </ol>
      </div>
    </div>

    {{-- 1. Login --}}
    <section id="login" class="mb-5">
      <h2 class="h3">1. Logging in</h2>
      <p>You don't need a password. On the login page, type your email address and press <strong>Send Login Link</strong>.</p>
      <ol>
        <li>Check your email for a message from the school.</li>
        <li>Tap the link inside it — that logs you straight in.</li>
      </ol>
      <div class="alert alert-info">
        <strong>Tip:</strong> the email can take a moment to arrive. If you ask for a link more than once, just open the most recent email — every link works. A login lasts a few hours, then you sign in again the same way.
      </div>
      <p><strong>There's no password to forget</strong> — the emailed link <em>is</em> how you sign in, so if you can read your school email you can always get back in. If the page says your email isn't recognised, ask an administrator to check the address on your account (see <a href="#teachers">Teachers</a>).</p>
      <p>What you see in the menu depends on your <a href="#roles">role</a>. Most teachers can mark attendance, record books, and see today's report; the reports, the edit grid, and the admin pages are for coordinators and administrators. If a page you expect is missing, your role doesn't include it — ask an administrator.</p>
      <figure class="figure w-100">
        <img src="/images/help/login.png" class="figure-img img-fluid rounded border shadow-sm" alt="The teacher login page with an email box and a Send Login Link button.">
        <figcaption class="figure-caption">The login page — enter your email and press Send Login Link.</figcaption>
      </figure>
    </section>

    {{-- 2. Mark attendance --}}
    <section id="mark" class="mb-5">
      <h2 class="h3">2. Marking attendance</h2>
      <p>After you log in you land on <strong>New Attendance</strong>. You can always get back to it from the <strong>Attendance</strong> menu at the top.</p>

      <h3 class="h5 mt-4">Step 1 — Choose the subject and class</h3>
      <p>Pick the <strong>Subject</strong> and <strong>Class</strong> you're teaching, then press <strong>Continue</strong>.</p>
      <figure class="figure w-100">
        <img src="/images/help/select-subject-class.png" class="figure-img img-fluid rounded border shadow-sm" alt="The subject and class selection page with two drop-down menus and a Continue button.">
        <figcaption class="figure-caption">Choose the subject and class, then Continue.</figcaption>
      </figure>

      <h3 class="h5 mt-4">Step 2 — Tap the students who are here</h3>
      <p>Each student is a button. <strong>Tap a name to mark them present</strong> — it turns green. Tap it again to unmark. The bar at the bottom always shows how many are present out of the whole class.</p>
      <ul>
        <li>Use the <strong>search box</strong> to find a name quickly in a big class.</li>
        <li><strong>Select all</strong> marks everyone; <strong>Clear</strong> unmarks everyone.</li>
        <li>Students already marked earlier today show up green when you open the page.</li>
      </ul>
      <p>When you're done, press <strong>Submit Attendance</strong>. That's it — you'll see a "submitted successfully" message.</p>
      <figure class="figure w-100">
        <img src="/images/help/mark-attendance.png" class="figure-img img-fluid rounded border shadow-sm" alt="The mark attendance page: student name buttons, some green (present), a search box, and a present count with a Submit button at the bottom.">
        <figcaption class="figure-caption">Green means present. The bottom bar counts present / total and holds the Submit button.</figcaption>
      </figure>
      <div class="alert alert-success">
        <strong>Changed your mind?</strong> Just open the same subject and class again, tap to fix who's present, and Submit again. It replaces today's record — you can't create duplicates.
      </div>
    </section>

    {{-- 3. Book distribution --}}
    <section id="books" class="mb-5">
      <h2 class="h3">3. Handing out books</h2>
      <p>Recording who received their books works exactly like marking attendance. Open <strong>Book Distribution</strong> from the top menu, pick the subject and class, then tap each student who got their books and press <strong>Submit Book Distribution</strong>. Books are tracked once per year, so you only need to do this once per student.</p>
      <figure class="figure w-100">
        <img src="/images/help/book-distribution.png" class="figure-img img-fluid rounded border shadow-sm" alt="The book distribution page with student name buttons and a given count, the same layout as marking attendance.">
        <figcaption class="figure-caption">Same tap-to-mark layout — the count shows how many have been given their books.</figcaption>
      </figure>
    </section>

    {{-- 4. Edit / back-fill --}}
    <section id="edit" class="mb-5">
      <h2 class="h3">4. Fixing or back-filling attendance</h2>
      <p>Missed marking a student on an earlier day? Use <strong>Edit Attendance</strong> (under the <strong>Attendance</strong> menu). It shows a grid of students down the side and each date across the top.</p>
      <ol>
        <li>Choose the subject and class and press <strong>Show</strong>.</li>
        <li><strong>Tick the boxes</strong> for the dates each student attended (or untick to remove one).</li>
        <li>Need a date that isn't there yet? Use <strong>Add a date</strong> to add a column.</li>
        <li>Press <strong>Save</strong> — every tick on the grid is saved at once.</li>
      </ol>
      <p>You can also search for a student by name using the box above the grid.</p>
      <figure class="figure w-100">
        <img src="/images/help/edit-attendance.png" class="figure-img img-fluid rounded border shadow-sm" alt="The edit attendance grid: student names down the left, dates across the top, and tick boxes in each cell, with a Save button.">
        <figcaption class="figure-caption">Tick the dates each student attended, then Save.</figcaption>
      </figure>
    </section>

    {{-- 5. Today's Report --}}
    <section id="todays-report" class="mb-5">
      <h2 class="h3">5. Today's Report</h2>
      <p>Under the <strong>Report</strong> menu, <strong>Today's Report</strong> shows one card per subject. Each badge reads <strong>present today / enrolled</strong>. Tap a subject total or a class to see the names of who's here today.</p>
      <figure class="figure w-100">
        <img src="/images/help/todays-report.png" class="figure-img img-fluid rounded border shadow-sm" alt="Today's Report: a card for each subject showing a present-over-enrolled badge and a list of classes with their own counts.">
        <figcaption class="figure-caption">A card per subject, with present / enrolled counts you can tap to see the names.</figcaption>
      </figure>
    </section>

    {{-- 6. Full Year Report --}}
    <section id="full-year-report" class="mb-5">
      <h2 class="h3">6. Full Year Report</h2>
      <p>Also under <strong>Report</strong>, <strong>Full Year Report</strong> gives the whole year for a subject: one row per student, one column per date the class met, a tick where they attended, and a <strong>Total days</strong> for each student. Choose a subject and press <strong>Show</strong>. You can search for a student and sort by name or total.</p>
      <figure class="figure w-100">
        <img src="/images/help/full-year-report.png" class="figure-img img-fluid rounded border shadow-sm" alt="Full Year Report: a table with students as rows, dates as columns, ticks for attendance, and a total-days column.">
        <figcaption class="figure-caption">Every student against every session, with a running total of days attended.</figcaption>
      </figure>
    </section>

    {{-- 7. Registration Sync --}}
    <section id="sync" class="mb-5">
      <h2 class="h3">7. Registration Sync</h2>
      <p>Students are added automatically from the registration system once a family has paid — you don't add students by hand. The <strong>Registration Sync</strong> page just shows when that last happened. It usually updates on its own; if you're expecting a new student and don't see them yet, press <strong>Sync now</strong>.</p>
      <figure class="figure w-100">
        <img src="/images/help/registration-sync.png" class="figure-img img-fluid rounded border shadow-sm" alt="The Registration Sync page showing when students were last synced and a Sync now button.">
        <figcaption class="figure-caption">Shows when students last synced from registration, with a Sync now button.</figcaption>
      </figure>
    </section>

    {{-- 8. Teachers --}}
    <section id="teachers" class="mb-5">
      <h2 class="h3">8. Teachers — adding &amp; removing</h2>
      <p class="text-body-secondary"><em>Administrators only — you'll see <strong>Admin → Teachers</strong> in the menu if this applies to you.</em></p>
      <p>This page lists everyone who can sign in, with their role and whether the account is active.</p>
      <p><strong>To add someone:</strong> click <strong>Add teacher</strong> and enter their name, email and <a href="#roles">role</a>. There's no password to set — they sign in from the login page with that email address, exactly like everyone else. Make sure the address is one they actually read, because that's where their login links go.</p>
      <p><strong>To remove someone:</strong> click <strong>Deactivate</strong>. No further login links will be sent to them, any link already sitting in their inbox stops working, and if they're signed in right now they're dropped on their next tap. The account is <strong>not</strong> deleted, on purpose: the audit log needs to keep naming a real person. <strong>Reactivate</strong> undoes it.</p>
      <div class="alert alert-warning">
        Two things the system won't let you do, to stop the school being locked out: you can't deactivate <strong>your own</strong> account, and you can't remove the <strong>last</strong> account that can manage teachers. Give someone else that permission first.
      </div>
    </section>

    {{-- 9. Roles --}}
    <section id="roles" class="mb-5">
      <h2 class="h3">9. Roles &amp; permissions</h2>
      <p class="text-body-secondary"><em>Administrators only — <strong>Admin → Roles &amp; Permissions</strong>.</em></p>
      <p>A <strong>role</strong> is a named set of permissions, and every teacher holds exactly one. Change a role and everyone in it changes with it straight away — nobody has to sign in again.</p>
      <p>Three roles come set up:</p>
      <ul>
        <li><strong>Teacher</strong> — marks attendance, records books, sees today's report. This is what everyone gets by default.</li>
        <li><strong>Coordinator</strong> — all of that plus editing past attendance, the full-year report, the details view, and Registration Sync.</li>
        <li><strong>Administrator</strong> — everything, plus teacher accounts, roles and the audit log.</li>
      </ul>
      <p><strong>Edit</strong> on a role opens a tick-box grid grouped by area — Attendance, Reports, Administration — where you turn each permission on or off. <strong>Add role</strong> makes a new one if none of the three fit. A role can only be deleted once nobody holds it.</p>
      <p>Every account and permission change is recorded in <strong>Admin → Audit Log</strong>: who did it, when, and what changed. That log is append-only — it can't be edited or deleted from inside the app.</p>
    </section>

    <hr>
    <p class="text-muted">Still stuck? Contact the school administrator and they'll help you out.</p>

  </div>
</div>
@endsection
