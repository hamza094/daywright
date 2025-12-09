@include('welcome.header')

<div class="container-fluid bg-white">
    <div class="home-content">
   <Section class="intro">
<div class="row">
    <div class="col-md-6 p-5">
       <div class="intro-left">
           <p class="intro-left_header">Work on big ideas,<br>without the busy work.
           </p>
           <span class="intro-left_border"></span>
           <p class="intro-left_para">DayWright is a Project Management Web System that helps teams to manage and track their whole projects in one place. With DayWright, you'll stay on top of everything.
           </p>

           <a href="/dashboard" target="_blank" rel="noopener noreferrer" class="intro-left_btn">Try For Free</a>
       </div>
   </div>
   <div class="col-md-6 padding-0">
       <div class="intro-right">
             <img src="{{asset('img/pf1.jpg')}}">
       </div>
   </div>
   </div>
   </Section>

   <SECTION class="about">
      <div class="heading">
        <div>Explore PMS</div>
        <span class="heading-border"></span>
      </div>
     <div class="row">
       <div class="col-md-6">
        <div class="about-img">
          <img src="{{asset('img/capture.jpg')}}">
        </div>
       </div>
       <div class="col-md-6">
        <div class="about-content">
         <div class="about-content-1">
         <span class="about-content_heading">Task</span>
         <p class="about-content_para">Create user stories and issues,plan sprints,and distribute tasks across your software team</p>
        </div>
         <div class="about-content-2">
         <span class="about-content_heading">Appointment</span>
         <p class="about-content_para">Create user stories and issues,plan sprints,and distribute tasks across your software team</p>
       </div>
       <div class="about-content-3">
         <span class="about-content_heading">Online Chat</span>
         <p class="about-content_para">Create user stories and issues,plan sprints,and distribute tasks across your software team</p>
       </div>
          <div class="about-more"><a href="/dashboard"><span>+</span>Explore all features</a></div>
        </div>
       </div>
     </div>
   </SECTION>

   <section class="p-5">
    <div class="heading">
        <div>Special Features</div>
        <span class="heading-border"></span>
      </div>
      <div>
        <div class="feature">
        <div class="row">
          <div class="col-md-6">
            <div class="feature-content">
              <span>Paypal Stripe Subscription</span>
              <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent condimentum lorem nisl, non bibendum tortor porta sed. Aliquam luctus at tortor sed dapibus. Phasellus molestie urna vitae leo porta.</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="feature-img">
              <img src="{{asset('img/Subscription.png')}}">
            </div>
          </div>
        </div>
      </div>
<div class="feature">
         <div class="row">
          <div class="col-md-6">
            <div class="feature-img">
              <img src="{{asset('img/activity.png')}}">
            </div>
          </div>
          <div class="col-md-6">
              <div class="feature-content">
              <span>Project Activity Feed</span>
              <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent condimentum lorem nisl, non bibendum tortor porta sed. Aliquam luctus at tortor sed dapibus. Phasellus molestie urna vitae leo porta.</p>
            </div>
          </div>
        </div>
      </div>
   </section>
    </div>
</div>
   <section class="footer">
     <footer class="page-footer font-small blue">
  <!-- Copyright -->
  <div class="footer-copyright text-center py-3 ft">© 2024 Copyright:
    <a href="/"> DayWright.com</a>
  </div>
  <!-- Copyright -->
</footer>
   </section>

    </body>
</html>
