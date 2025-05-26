/*==============================================================*/
/* Nom de SGBD :  MySQL 5.0                                     */
/* Date de cr�ation :  22/05/2025 10:05:37                      */
/*==============================================================*/
create database [unihousing];

alter table house 
   drop foreign key fk_house_house_cit_city;

alter table house 
   drop foreign key fk_house_house_pro_proprety;

alter table house 
   drop foreign key fk_house_owner_hou_owner;

alter table house_property_pictures 
   drop foreign key fk_house_pr_house_pro_proprety;

alter table house_property_pictures 
   drop foreign key fk_house_pr_house_pro_house;

alter table owner_student 
   drop foreign key fk_owner_st_owner_stu_student;

alter table owner_student 
   drop foreign key fk_owner_st_owner_stu_owner;

alter table picture 
   drop foreign key fk_picture_owner_pic_owner;

alter table student 
   drop foreign key fk_student_picture_s_picture;

alter table student_house 
   drop foreign key fk_student__student_h_house;

alter table student_house 
   drop foreign key fk_student__student_h_student;

/*==============================================================*/
/* Table : city                                                 */
/*==============================================================*/
create table city
(
   city_id              int not null,
   city_name            text,
   primary key (city_id)
);

/*==============================================================*/
/* Table : house                                                */
/*==============================================================*/
create table house
(
   house_id             int not null,
   city_id              int not null,
   proprety_type_id     int not null,
   owner_id             int not null,
   house_title          text,
   house_price          float,
   house_location       text,
   house_badroom        int,
   house_bathroom       int,
   house_description    text,
   primary key (house_id)
);

/*==============================================================*/
/* Table : house_property_pictures                              */
/*==============================================================*/
create table house_property_pictures
(
   house_id             int not null,
   proprety_pictures_id int not null,
   house_property_pictures_date timestamp,
   primary key (house_id, proprety_pictures_id)
);

/*==============================================================*/
/* Table : owner                                                */
/*==============================================================*/
create table owner
(
   owner_id             int not null,
   owner_name           text,
   owner_email          text,
   owner_password       text,
   primary key (owner_id)
);

/*==============================================================*/
/* Table : owner_student                                        */
/*==============================================================*/
create table owner_student
(
   owner_id             int not null,
   student_id           int not null,
   owner_student_date   timestamp,
   primary key (owner_id, student_id)
);

/*==============================================================*/
/* Table : picture                                              */
/*==============================================================*/
create table picture
(
   picture_id           int not null,
   owner_id             int not null,
   picture_url          text,
   primary key (picture_id)
);

/*==============================================================*/
/* Table : proprety_pictures                                    */
/*==============================================================*/
create table proprety_pictures
(
   proprety_pictures_id int not null,
   proprety_pictures_name text,
   primary key (proprety_pictures_id)
);

/*==============================================================*/
/* Table : proprety_type                                        */
/*==============================================================*/
create table proprety_type
(
   proprety_type_id     int not null,
   proprety_type_name   text,
   primary key (proprety_type_id)
);

/*==============================================================*/
/* Table : student                                              */
/*==============================================================*/
create table student
(
   student_id           int not null,
   picture_id           int not null,
   student_name         text,
   student_email        text,
   student_password     text,
   primary key (student_id)
);

/*==============================================================*/
/* Table : student_house                                        */
/*==============================================================*/
create table student_house
(
   student_id           int not null,
   house_id             int not null,
   student_house_date   date,
   primary key (student_id, house_id)
);

alter table house add constraint fk_house_house_cit_city foreign key (city_id)
      references city (city_id) on delete restrict on update restrict;

alter table house add constraint fk_house_house_pro_proprety foreign key (proprety_type_id)
      references proprety_type (proprety_type_id) on delete restrict on update restrict;

alter table house add constraint fk_house_owner_hou_owner foreign key (owner_id)
      references owner (owner_id) on delete restrict on update restrict;

alter table house_property_pictures add constraint fk_house_pr_house_pro_proprety foreign key (proprety_pictures_id)
      references proprety_pictures (proprety_pictures_id) on delete restrict on update restrict;

alter table house_property_pictures add constraint fk_house_pr_house_pro_house foreign key (house_id)
      references house (house_id) on delete restrict on update restrict;

alter table owner_student add constraint fk_owner_st_owner_stu_student foreign key (student_id)
      references student (student_id) on delete restrict on update restrict;

alter table owner_student add constraint fk_owner_st_owner_stu_owner foreign key (owner_id)
      references owner (owner_id) on delete restrict on update restrict;

alter table picture add constraint fk_picture_owner_pic_owner foreign key (owner_id)
      references owner (owner_id) on delete restrict on update restrict;

alter table student add constraint fk_student_picture_s_picture foreign key (picture_id)
      references picture (picture_id) on delete restrict on update restrict;

alter table student_house add constraint fk_student__student_h_house foreign key (house_id)
      references house (house_id) on delete restrict on update restrict;

alter table student_house add constraint fk_student__student_h_student foreign key (student_id)
      references student (student_id) on delete restrict on update restrict;

