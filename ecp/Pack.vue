<template>
  <q-page padding>
    <!-- Диалог восстановления удаленных заявок -->
    <q-dialog v-model="dialogRestoreTicketsDeleted" persistent>
      <q-card>
        <q-card-section class="row items-center bg-red text-white">
          <div class="text-h6">Список удаленных заявок в пачке</div>
        </q-card-section>

        <q-card-section>
          <q-list bordered>
            <q-item
              v-for="(ticket, index) in collectionTicketsDeleted"
              :key="index"
            >
              <q-item-section>
                <q-item-label>{{ ticket.OrgTicketCode }}</q-item-label>
                <q-item-label caption>{{ ticket.Login }}: {{ ticket.StatusDate }}</q-item-label>
              </q-item-section>
              <q-item-section avatar>
                <q-btn
                  flat
                  round
                  color="red"
                  icon="fas fa-trash-restore-alt"
                  :loading="isLoadingRestoreTicket"
                  @click.native="restoreTicket({ ticket: ticket, index: index })"
                />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Закрыть" color="grey" v-close-popup @click="dialogRestoreTicketsDeleted = !dialogRestoreTicketsDeleted" />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <!-- конец Диалог восстановления удаленных заявок -->

    <!-- Диалог подтверждения удаления заявки -->
    <q-dialog v-model="isDeletedTicket" persistent>
      <q-card>
        <q-card-section class="row items-center bg-red text-white">
          <div class="text-h6">Вы собираетесь удалить заявку из пачки.<br>Вы уверены?</div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Удалить" color="red" v-close-popup @click="runDeletedTicket" />
          <q-btn flat label="Отмена" color="grey" v-close-popup @click="isDeletedTicket = !isDeletedTicket" />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <!-- конец Диалог подтверждения удаления заявки -->

    <!-- Диалог подтверждения удаления исполнителя -->
    <q-dialog v-model="dialogExecutorDelete" persistent>
      <q-card>
        <q-card-section class="row items-center bg-red text-white">
          <div class="text-h6">Вы хотите удалить исполнителя {{ executorDelete.executor.FIO }} с заявки {{ executorDelete.OrgTicketCode }}.<br>Вы уверены?</div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Отмена" color="grey" v-close-popup @click="dialogExecutorDelete = !dialogExecutorDelete" />
          <q-btn flat label="Удалить" color="red" v-close-popup @click="cancelOwnerSend" />
        </q-card-actions>
      </q-card>
    </q-dialog>
    <!-- конец Диалог подтверждения удаления исполнителя -->

<!--    Диалог поиска заявки-->
    <q-dialog v-model="dialogAddTicket" persistent>
      <q-card style="min-width: 350px">
        <q-card-section>
          <q-input dense v-model="dialogAddTicketNumber" autofocus label="Номер заявки" @keyup.enter="searchTicket" />
        </q-card-section>

        <q-card-section v-if="dialogAddTicketSearch">
          <q-chip icon="search" color="orange">Ищем заявку</q-chip>

          <q-spinner
            color="red"
            size="2em"
            :thickness="2"
          />
        </q-card-section>

        <q-card-section v-if="dialogAddTicketNotFound">
          <q-chip icon="search" color="red">Заявка не найдена</q-chip>
        </q-card-section>

        <q-card-actions align="right" class="text-primary">
          <q-btn flat label="Найти" @click="searchTicket" />
          <q-btn flat label="Отмена" v-close-popup @click="dialogAddTicketClose" />
        </q-card-actions>
      </q-card>
    </q-dialog>
<!--  конец Диалог поиска заявки  -->

<!--    Диалог просмотра найденной заявки-->
    <q-dialog v-model="dialogInfoTicket">
      <q-card>
        <q-card-section>
          <div class="text-h6">Заявка найдена</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-list bordered separator>
            <q-item>
              <q-item-section>
                <q-item-label>{{ foundTicket.CstWorkName }}</q-item-label>
                <q-item-label caption>Тип заявки</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section>
                <q-item-label>{{ foundTicket.OrgTicketCode }}</q-item-label>
                <q-item-label caption>Заявка</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section>
                <q-item-label>{{ foundTicket.OrgStatusCodeText }}</q-item-label>
                <q-item-label caption>Статус</q-item-label>
              </q-item-section>
            </q-item>

            <q-item>
              <q-item-section>
                <q-item-label>{{ foundTicket.OrgClientFIO }}</q-item-label>
                <q-item-label caption>Абонент</q-item-label>
              </q-item-section>
            </q-item>

          </q-list>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Добавить" color="primary" v-close-popup @click="addFoundTicket" />
          <q-btn flat label="Отмена" color="primary" v-close-popup @click="cancelFoundTicket" />
        </q-card-actions>
      </q-card>
    </q-dialog>
<!--  конец  Диалог просмотра найденной заявки-->

<!--    Диалог проверки доработки документа-->
    <q-dialog
      v-model="alertReWork"
      @before-hide="closeAlertReWork"
      persistent
      transition-show="scale"
      transition-hide="scale"
    >
      <q-card class="bg-red-5 text-white" style="width: 300px">
        <q-card-section>
          <div class="text-h6">Внимание</div>
        </q-card-section>

        <q-card-section>
          Нельзя отправить документ на доработку без комментариев к нему.
        </q-card-section>

        <q-card-actions align="right" class="bg-white text-red-5">
          <q-btn flat label="OK" v-close-popup @click="closeAlertReWork"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
<!--  конец Диалог проверки доработки документа-->

<!--    Диалог проверки корректности документа-->
    <q-dialog
      v-model="alertCorrectly"
      @before-hide="closeAlertCorrectly"
      persistent
      transition-show="scale"
      transition-hide="scale"
    >
      <q-card class="bg-red-5 text-white" style="width: 300px">
        <q-card-section>
          <div class="text-h6">Внимание</div>
        </q-card-section>

        <q-card-section>
          Нельзя подтвердить корректность документа когда есть аннотации к нему.
        </q-card-section>

        <q-card-actions align="right" class="bg-white text-red-5">
          <q-btn flat label="OK" v-close-popup @click="closeAlertCorrectly"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
<!--  конец  Диалог проверки корректности документа-->

    <div class="q-pa-md" v-if="!isViewImage">
      <q-list bordered>
        <q-expansion-item>
          <template v-slot:header>
            <q-item-section avatar>
              <q-avatar size="32px">
                <img src="statics/logo/bee.png">
              </q-avatar>
            </q-item-section>

            <q-item-section>
              <div>
                  Пакет документов: {{ getCodePacket }} &nbsp;
                  <q-icon
                    v-if="collectionTicketsDeleted.length > 0"
                    color="red"
                    name="far fa-trash-alt"
                    @click="dialogRestoreTicketsDeleted = !dialogRestoreTicketsDeleted"
                  >
                    <q-tooltip>В пачке есть удаленные заявки</q-tooltip>
                  </q-icon>
                </div>
            </q-item-section>

            <q-badge
              color="red-5"
              floating
            >
              <q-icon name="fa fa-times" size="xs" @click="back"/>
            </q-badge>
          </template>

          <q-card>
            <q-card-section>

            </q-card-section>
          </q-card>
        </q-expansion-item>

        <q-separator />

        <q-expansion-item default-opened>
          <template v-slot:header>
            <q-item-section avatar>
              <q-avatar icon="scanner" text-color="green-5"/>
            </q-item-section>

            <q-item-section>
              Скан-копии документов&nbsp;
              <q-spinner
                v-if="isSpinnerLoadDocs"
                color="red-5"
                size="2em"
                :thickness="2"
              />
            </q-item-section>
          </template>

          <q-card v-if="isLoadDocs">
            <q-card-section
              v-if="getDocs"
            >
              <docsCollection :docs="getDocsByVersion" @docCheck="documentCheck($event)"></docsCollection>
            </q-card-section>

            <q-card-section
              v-if="getDocs"
            >
              <q-toggle
                v-model="checkNotProvided"
                color="red"
                label="Не полный комплект"
              />

              <q-select
                v-if="checkNotProvided"
                v-model="notProvided"
                dense
                multiple
                clearable
                :options="optionsNotProvided"
                label="Список отсутствующих документов"
                style="width: 350px"
              />
            </q-card-section>
          </q-card>

          <q-card>
            <q-card-section>
              <pack-comments :docs="getDocs"></pack-comments>
            </q-card-section>
          </q-card>
        </q-expansion-item>

        <q-separator />

        <q-spinner
          v-if="isSpinnerLoadTickets"
          color="red-5"
          size="2em"
          :thickness="2"
        />

        <q-expansion-item
          v-show="isLoadTickets"
          v-for="item in getTicketsCollection"
          v-bind:key="item.OrgTicketBDID"
          default-opened
          class="header-background"
        >
          <template v-slot:header>
            <q-item-section avatar>
              <q-avatar icon="computer" text-color="green-5"/>
            </q-item-section>

            <q-item-section>
              <q-item-label>
                {{ item.OrgTicketTypeText}}: {{ item.OrgTicketCode }}
                <q-btn icon="far fa-copy" color="grey-7" flat size="sm" @click.stop="copyTicket(item.OrgTicketCode)">
                  <q-tooltip>Скопировать номер тикета</q-tooltip>
                </q-btn>

                <q-btn
                  v-if="!spinnerUpdateInfoTicket"
                  icon="fas fa-sync"
                  color="grey-7"
                  flat
                  size="sm"
                  @click.stop="updateTicket(item)"
                >
                  <q-tooltip>Обновить информацию из системы заказчика</q-tooltip>
                </q-btn>

                <q-spinner
                  v-if="spinnerUpdateInfoTicket"
                  color="red-5"
                  size="2em"
                  :thickness="2"
                />

                <q-btn icon="fas fa-search" color="grey-7" flat size="sm" @click.stop="openHD(item.OrgTicketCode)">
                  <q-tooltip>Перейти в заявку в ХД</q-tooltip>
                </q-btn>

                <q-btn
                  icon="far fa-trash-alt"
                  color="grey-7"
                  flat
                  size="sm"
                  @click.stop="deleteTicket(item)"
                >
                  <q-tooltip>Удалить заявку</q-tooltip>
                </q-btn>

                <q-btn
                  v-if="item.StatusLO !== 'Сдан' && item.StatusLO !== 'Закрыт' && item.StatusLO !== 'Наряд сдан'"
                  icon="fas fa-check"
                  color="grey-7"
                  flat
                  size="sm"
                  :disabled="item.Executors.length === 0"
                  @click.stop="ticketCompleted(item)"
                >
                  <q-tooltip>Передать статус ВЫПОЛНЕНО</q-tooltip>
                </q-btn>

                <q-btn
                    v-if="item.StatusLO !== 'Сдан' && item.StatusLO !== 'Закрыт' && item.StatusLO !== 'Наряд сдан'"
                  icon="far fa-times-circle"
                  color="grey-7"
                  flat
                  size="sm"
                  :disabled="item.Executors.length === 0"
                  @click.stop="notCompleted(item)"
                >
                  <q-tooltip>Передать статус НЕ ВЫПОЛНЕНО</q-tooltip>
                </q-btn>

              </q-item-label>
            </q-item-section>

            <q-item-section>
              <q-item-label align="right">
                <q-chip v-if="item.StatusLO === 'Не выполнен'" color="red" text-color="white">
                  ЛО: {{ item.StatusLO }}
                </q-chip>

                <q-chip v-else-if="item.StatusLO === 'В работе' || item.StatusLO === 'Новый'" color="orange" text-color="white">
                  ЛО: {{ item.StatusLO }}
                </q-chip>

                <q-chip v-else color="green-5" text-color="white">
                  ЛО: {{ item.StatusLO }}
                </q-chip>

                <q-chip v-if="item.OrgStatusCodeText === 'Закрыта' || item.OrgStatusCodeText === 'Подключен'" color="green-5" text-color="white">
                  ВК: {{ item.OrgStatusCodeText }}
                </q-chip>

                <q-chip v-else color="orange" text-color="white">
                  ВК: {{ item.OrgStatusCodeText }}
                </q-chip>
              </q-item-label>
            </q-item-section>
          </template>

          <q-card>
            <q-separator/>

            <q-card-section>
              <q-icon name="fa fa-laptop-house"></q-icon>
              &nbsp;
              {{ item.Address }}

              <q-space/>

              <div
                v-if="item.Executors !== undefined && item.Executors.length > 0"
              >
                <div
                  v-for="(executor, key) in item.Executors"
                  :key="key"
                >
                  <q-icon name="fa fa-user-cog"></q-icon>
                  &nbsp;
                  {{ executor.FIO }}

                  <q-btn
                    flat
                    icon="fas fa-times"
                    size="xs"
                    color="grey"
                    @click="cancelOwner({ executor: executor, OrgTicketCode: item.OrgTicketCode })"
                    :loading="loadingDelete"
                  >
                    <q-tooltip>Удалить исполнителя</q-tooltip>
                  </q-btn>
                </div>

                <q-banner
                  v-if="executorDeleteError"
                  inline-actions
                  class="text-white bg-red"
                >
                  Ошибка при удалении исполнителя. Ошибка: {{ executorDeleteErrorText }}
                </q-banner>
              </div>

              <div
                v-if="item.Executors === undefined || item.Executors.length === 0"
              >
                <q-icon name="fa fa-user-cog" color="red" />
                &nbsp;
                <span style="color: red;">Отсутствует исполнитель</span>
              </div>

            </q-card-section>

            <q-separator/>

            <q-card-section>
              <q-spinner
                v-if="!isLoadWorks"
                color="red-5"
                size="2em"
                :thickness="2"
              />

              <listWorks
                v-if="isLoadWorks"
                :works="works"
                :ticket="item.OrgTicketCode"
                :refs="item.OrgTicketCode"
                @addWork="addWork($event)"
                @deleteWork="deleteWork($event)"
                @changeWork="changeWork($event)"
              />

              <addWork
                v-if="isLoadAddWorks && listAddWorks[item.OrgTicketCode]"
                :dialog="isLoadAddWorks"
                :ticket="item.OrgTicketCode"
                :works="listAddWorks[item.OrgTicketCode]"
                @add="addDialogAddWork($event)"
                @cancel="cancelDialogAddWork"
              />

              <changeWork
                v-if="isLoadChangeWorks && listChangeWorks[item.OrgTicketCode]"
                :dialog="isLoadChangeWorks"
                :ticket="item.OrgTicketCode"
                :works="listChangeWorks[item.OrgTicketCode]"
                @cancel="cancelDialogChangeWork"
                @change="changeWorkSend($event)"
              />
            </q-card-section>

          </q-card>
        </q-expansion-item>

        <q-separator />

        <q-card
          v-if="isLoadWorks"
        >
          <q-card-section>
            <q-banner
              v-if="!provenJobs"
              inline-actions
              class="text-white bg-red"
            >
              Необходимо подтвердить работы
              <template v-slot:action>
                <q-btn
                  flat icon="fa fa-check"
                  color="white"
                  label="Подтвердить"
                  :loading="loadingProvenJobs"
                  @click="addProvenJobs"
                />
              </template>
            </q-banner>

            <q-banner
              v-else
              class="text-white bg-green"
            >
              Работы подтверждены
            </q-banner>

          </q-card-section>
        </q-card>

        <q-separator />

        <q-expansion-item
          v-show="isLoadDocs"
        >
          <template v-slot:header>
            <q-item-section avatar>
              <q-avatar icon="fa fa-portrait" text-color="green-5"/>
            </q-item-section>

            <q-item-section>
              Проверка паспортных данных
            </q-item-section>
            <q-chip
              :color="(getPassportStatus) ? 'red-5' : 'green-5'" text-color="white"
              v-if="getPassportStatus != null"
            >
              {{ (getPassportStatus) ? 'Ошибка' : 'Успешно' }}
            </q-chip>
            <q-spinner
              v-if="getPassportStatus === null"
              color="red-5"
              size="2em"
              :thickness="2"
            />
          </template>

          <q-card>
            <q-card-section>
              <q-markup-table dense>
                <thead>
                <tr>
                  <th class="text-left">Параметр</th>
                  <th class="text-center">Статус</th>
                </tr>
                </thead>
                <tbody>
                <tr
                  v-for="(item, key) in getPassportData"
                  v-bind:key="key"
                  :class="(item === 'Ошибка') ? 'text-red-5' : 'black'"
                >
                  <td class="text-left">{{ key }}</td>
                  <td class="text-center">{{ item }}</td>
                </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>

        </q-expansion-item>
        <q-separator />
      </q-list>

      <br><br>

      <div class="q-pa-md q-gutter-sm">
        <q-btn
          v-if="isDocsRework || checkNotProvided"
          outline
          color="red-5"
          :loading="isSendPack"
          v-on:click="sendPack('rework')"
        >
          На доработку
        </q-btn>

        <q-btn
          v-if="isDocsCorrectly && !checkNotProvided && provenJobs"
          outline
          color="green-5"
          :loading="isSendPack"
          v-on:click="sendPack('correctly')"
        >
          Корректно
        </q-btn>
      </div>
    </div>

    <view-picture
      v-if="isViewImage"
      :src="pictureSrc"
      :path="docInfo.ContractDocPath"
      :comments="getComments"
      :btn="viewPictureBtn"
      :status="docInfo.ContractDocStatus"
      :docInfo="docInfo"
      @close="closePicture"
      @correctly="correctly"
      @rework="rework($event)"
    />

  </q-page>
</template>

<script>
import 'vue-annotorious'
import listWorks from '../components/listWorks/ListWorks'
import addWork from '../components/listWorks/AddWork'
import changeWork from '../components/listWorks/ChangeWork'
import packComments from '../components/pack/Comments'
import docsCollection from '../components/docs/DocsCollection'
import viewPicture from '../components/viewPicture/ViewPicture'
import _ from 'lodash'
import { copyToClipboard, date } from 'quasar'

export default {
  name: 'Pack',
  data () {
    return {
      docGuid: 1,
      isDocsRework: false,
      isDocsCorrectly: false,
      isSendPack: false,
      docs: this.$store.getters['pack/docsCollection'],
      notProvided: null,
      checkNotProvided: false,
      optionsNotProvided: [],
      viewPictureBtn: true,
      loadingProvenJobs: false,
      dialogAddTicket: false,
      dialogAddTicketNumber: '',
      dialogAddTicketSearch: false,
      dialogInfoTicket: false,
      dialogAddTicketNotFound: false,
      foundTicket: {},
      isDeletedTicket: false,
      deletedTicket: false,
      deleteTicketNumber: null,
      dialogExecutorDelete: false,
      executorDelete: { executor: {} },
      executorDeleteTicket: null,
      executorDeleteFIO: null,
      loadingDelete: false,
      executorDeleteError: false,
      executorDeleteErrorText: null,
      spinnerUpdateInfoTicket: false,
      // isTicketsDeleted: false,
      collectionTicketsDeleted: [],
      dialogRestoreTicketsDeleted: false,
      isLoadingRestoreTicket: false
    }
  },

  components: {
    listWorks,
    addWork,
    changeWork,
    packComments,
    docsCollection,
    viewPicture
  },

  computed: {
    getCodePacket () {
      return this.$store.getters['pack/codePacket']
    },
    getDocs () {
      return this.$store.getters['pack/docsCollection']
    },
    getDocsByVersion () {
      return this.$store.getters['pack/docsCollectionByVersion']
    },
    isLoadDocs () {
      return this.$store.getters['pack/isLoadDocs']
    },
    isLoaderImage () {
      return this.$store.getters['pack/isLoaderImage']
    },
    dialogDocumentCheckView () {
      return this.$store.getters['documentCheck/dialogDocumentCheckView']
    },
    isSpinnerLoadDocs () {
      return this.$store.getters['pack/isSpinnerLoadDocs']
    },
    dialogDocumentCheckMaximizedToggle () {
      return this.$store.getters['documentCheck/dialogDocumentCheckMaximizedToggle']
    },
    getDocumentGuid () {
      return this.$store.getters['documentCheck/documentGuid']
    },
    isLoadTickets () {
      return this.$store.getters['pack/isLoadTickets']
    },
    getTicketsCollection () {
      return this.$store.getters['pack/ticketsCollection']
    },
    isSpinnerLoadTickets () {
      return this.$store.getters['pack/isSpinnerLoadTickets']
    },
    isViewImage () {
      return this.$store.getters['pack/isViewImage']
    },
    getImagePath () {
      return this.$store.getters['pack/imagePath']
    },
    alertReWork () {
      return this.$store.getters['pack/isDialogReWork']
    },
    alertCorrectly () {
      return this.$store.getters['pack/isDialogCorrectly']
    },
    getImageSrc () {
      return this.$store.getters['pack/imageSrc']
    },
    getPassportData () {
      return this.$store.getters['pack/passportData']
    },
    getPassportStatus () {
      return this.$store.getters['pack/passportStatus']
    },
    isLoadWorks () {
      return this.$store.getters['work/isLoadWorks']
    },
    works () {
      return this.$store.getters['work/works']
    },
    isLoadAddWorks () {
      return this.$store.getters['work/isLoadAddWorks']
    },
    listAddWorks () {
      return this.$store.getters['work/listAddWorks']
    },
    isLoadChangeWorks () {
      return this.$store.getters['work/isLoadChangeWorks']
    },
    listChangeWorks () {
      return this.$store.getters['work/listChangeWorks']
    },
    pictureSrc () {
      return this.$store.getters['document/pictureSrc']
    },
    docInfo () {
      return this.$store.getters['document/info']
    },
    getComments () {
      return this.$store.getters['document/comments']
    },
    provenJobs () {
      return this.$store.getters['work/provenJobs']
    }
  },

  created () {
    const codePacket = this.$store.getters['pack/codePacket']
    this.$store.dispatch('pack/getDocs', codePacket)
      .then((response) => {
        this.$store.dispatch('documents/getNotProvided', response.data.original)
          .then((response) => {
            const res = []
            _.each(response.data, (item) => {
              res.push({
                label: item.ContractDocTypeName,
                value: item.ContractDocTypeCode
              })
            })
            this.optionsNotProvided = res
          })

        this.$store.dispatch('documents/getDocsByVersion', response.data.original)
          .then(() => {
            const ticketsCollection = this.$store.getters['pack/ticketsCollection']
            this.$store.dispatch('rgks/getTicketsInfo', ticketsCollection)
              .then(() => {
                this.$store.dispatch('work/isLoadWorks', true)
                  .then(() => {
                    this.pack = true
                    this.loadingPack = false
                  })
              })
            this.checkCorrectlyReworkDocs()
          })
      })

    this.$store.dispatch('pack/getTicketsDeleted', codePacket)
      .then((response) => {
        if (response.length > 0) {
          this.collectionTicketsDeleted = response
        } else {
          this.collectionTicketsDeleted = []
        }
      })
      .catch(() => {
        // console.log
      })
  },

  mounted () {
    const annotorious = document.createElement('script')
    annotorious.setAttribute('src', 'https://welcome.nvbs.ru/annotorious/annotorious.min.js')
    document.head.appendChild(annotorious)
  },

  methods: {
    onAnnotationCreate () {
      this.annos = window.anno.getAnnotations()
      this.$store.dispatch('pack/setAnnotationCollection', this.annos)
    },

    documentCheck (data) {
      this.viewPictureBtn = (data.version !== 'others')
      this.$store.dispatch('pack/viewPicture', data.doc)
    },

    back () {
      this.isDeletedTicket = false
      this.deletedTicket = false
      this.$store.dispatch('pack/setTicketsCollection', [])
      this.$store.commit('pack/docsCollectionByVersion', [])
      this.$store.dispatch('pack/setDocsCollection', [])
      this.$store.dispatch('pack/setIsSpinnerLoadTickets', true)
      this.$store.dispatch('pack/setIsLoadTickets', false)
      this.$store.dispatch('pack/setIsSpinnerLoadDocs', true)
      this.$store.dispatch('pack/setIsLoadDocs', false)
      this.$store.dispatch('pack/setIsViewImage', false)
      this.$store.dispatch('pack/setImagePath', '')
      this.$store.dispatch('pack/setAnnotationCollection', [])
      this.$store.dispatch('pack/setIsPackCorrectly', true)
      this.$store.dispatch('pack/passportData', [])
      this.$store.dispatch('pack/passportStatus', null)
      this.$store.dispatch('work/clear', null, { root: true })
      this.$router.push({ name: 'processing' })
    },

    rework (event) {
      if (event.action === '') {
        this.$store.dispatch('pack/reworkByVersion')
          .then(() => {
            this.closeDoc()
            this.$store.dispatch('document/clear')
            this.checkCorrectlyReworkDocs()
          })
      } else if (event.action === 'new') {
        this.$store.dispatch('pack/createNewVersionRework', event.doc)
          .then(() => {
            this.$store.dispatch('pack/getDocs', event.doc.QRCodePacket)
              .then(() => {
                this.$store.dispatch('documents/getDocsByVersion', this.$store.getters['pack/docsCollection'])
                  .then(() => {
                    this.$store.dispatch('document/clear')
                    this.$store.commit('documentComment/isChanged', false)
                    this.closeDoc()
                    this.$store.dispatch('document/isLoadingNewRework', false)
                  })
              })
          })
      }
    },

    closeAlertReWork () {
      this.$store.dispatch('pack/setIsDialogReWork', false)
    },

    correctly () {
      this.$store.dispatch('pack/correctlyByVersion')
        .then(() => {
          this.closeDoc()
          this.$store.dispatch('document/clear')
          this.checkCorrectlyReworkDocs()
        })
    },

    closeAlertCorrectly () {
      this.$store.dispatch('pack/setIsDialogCorrectly', false)
    },

    closeDoc () {
      this.$store.dispatch('pack/clearImage')
      this.$store.dispatch('pack/setDocumentGuid', '')
      this.$store.dispatch('pack/setIsViewImage', false)
      this.$store.dispatch('pack/setAnnotationCollection', [])
      this.$store.dispatch('pack/setImageSrc', '')
      // this.$store.dispatch('pack/passportData', [])
      // this.$store.dispatch('pack/passportStatus', null)
    },

    addWork (ticket) {
      this.$store.dispatch('work/addWork', ticket)
    },

    cancelDialogAddWork () {
      this.$store.dispatch('work/cancelDialogAddWork', false)
    },

    addDialogAddWork (works) {
      this.$store.dispatch('work/addWorks', works)
    },

    deleteWork (work) {
      this.$store.dispatch('work/deleteWork', work)
    },

    cancelDialogChangeWork () {
      this.$store.dispatch('work/cancelDialogChangeWork', false)
    },

    changeWork (work) {
      this.$store.dispatch('work/changeWork', work)
    },

    changeWorkSend (work) {
      this.$store.dispatch('work/isLoadChangeWorks', false)
      this.$store.dispatch('work/changeWorkSend', work)
        .then((response) => {
          //
        })
        .catch(() => {
          //
        })
    },

    isRework () {
      const docs = this.$store.getters['pack/docsCollection']

      const correctly = _.filter(docs, (item) => {
        return item.isCorrectly
      })

      this.isReworkPack = correctly.length !== docs.length
    },

    isDocsProcessed () {
      const docs = this.$store.getters['pack/docsCollection']

      const processed = _.filter(docs, (item) => {
        return item.ContractDocStatus !== 'Назначен'
      })

      this.isDocsCorrectly = processed.length === docs.length
    },

    /**
     * Отправляем пачку в проверку РГКС
     * отмечаем все документы статусом Проверен и записываем документы для загрузки в ХД
     * @param action
     */
    sendPack (action) {
      this.isSendPack = true

      if (!this.notProvided && this.checkNotProvided) {
        this.$q.notify({
          color: 'red-5',
          textColor: 'white',
          icon: 'fas fa-times',
          message: 'Выбран не полный комплект, но не указаны отсутствующие документы.',
          position: 'top'
        })

        this.isSendPack = false
      } else {
        this.$store.dispatch('pack/sendPack', {
          action: action,
          notProvided: this.notProvided
        })
          .then(() => {
            this.back()

            this.isSendPack = false
            if (action === 'correctly') {
              this.$q.notify({
                color: 'green-5',
                textColor: 'white',
                icon: 'fas fa-check-circle',
                message: 'Пачка документов подтверждена',
                position: 'top'
              })
            } else if (action === 'rework') {
              this.$q.notify({
                color: 'green-5',
                textColor: 'white',
                icon: 'fas fa-check-circle',
                message: 'Пачка документов отправлена на доработку',
                position: 'top'
              })
            }
          })
          .catch((error) => {
            this.$q.notify({
              color: 'red-5',
              textColor: 'white',
              html: true,
              message: 'При подтверждении пачки произошла ошибка.<p>Ошибка: ' + error.data.detail + '</p><p>Попробуйте позже или обратитесь к администратору <a href="https://support.nvbs.ru" target="_blank">support.nvbs.ru</a></p>',
              position: 'top',
              timeout: 0,
              actions: [
                { label: 'Закрыть', color: 'white' }
              ]
            })

            this.loadingProvenJobs = false
          })
          .finally(() => {
            this.isSendPack = false
          })
      }
    },

    closePicture () {
      this.$store.commit('pack/setIsViewImage', false)
      this.$store.dispatch('document/clear')
    },

    checkCorrectlyReworkDocs () {
      const docs = this.$store.getters['pack/docsCollectionByVersion']
      let countCorrectly = 0
      let countRework = 0

      _.each(docs, (doc) => {
        if (doc.last.isCorrectly) {
          countCorrectly++
        } else if (!doc.last.isCorrectly && doc.last.ContractDocStatus === 'Доработка') {
          countRework++
        }
      })

      if (countCorrectly === _.size(docs) && countRework === 0) {
        this.isDocsCorrectly = true
        this.isDocsRework = false
      }
      if (countRework > 0) {
        this.isDocsRework = true
        this.isDocsCorrectly = false
      }
    },

    copyTicket (ticket) {
      copyToClipboard(ticket)
        .then(() => {
          this.$q.notify({
            color: 'green-5',
            textColor: 'white',
            icon: 'fas fa-check-circle',
            message: 'Номер тикета скопирован',
            position: 'top'
          })
        })
        .catch(() => {
          this.$q.notify({
            color: 'red-5',
            textColor: 'white',
            icon: 'fas fa-check-circle',
            message: 'Ошибка при копировании.',
            position: 'top'
          })
        })
    },

    openHD (ticket) {
      window.open('http://10.21.129.1/findticket.pl?TT=' + ticket, '_blank')
    },

    addProvenJobs () {
      this.loadingProvenJobs = true

      this.$store.dispatch('pack/confirmTickets')
        .then((response) => {
          this.$q.notify({
            color: 'green-5',
            textColor: 'white',
            icon: 'fas fa-check-circle',
            message: 'Заявки подтверждены.',
            position: 'top'
          })
        })
        .catch((error) => {
          // вывод сообщения обернул в условие, потому что не понятно почему оно выводится при условии
          // что confirmTickets не возвращет ошибки и все отрабатывается и записывается в таблицы
          if (error.response !== undefined) {
            this.$q.notify({
              color: 'red-5',
              textColor: 'white',
              icon: 'fas fa-check-circle',
              message: 'Ошибка при подтверждении заявок в пачке. Ошибка: ' + error.response,
              position: 'top'
            })
          }
        })

      this.$store.dispatch('work/addProvenJobs', 'Подтвержден')
        .then(() => {
          //
        })
        .catch(() => {
          //
        })
        .finally(() => {
          this.loadingProvenJobs = false
        })
    },

    notCompleted (ticket) {
      this.$store.dispatch('pack/notCompleted', ticket)
        .then((result) => {
          if (result.success !== undefined) {
            const tickets = this.$store.getters['pack/ticketsCollection']

            _.find(tickets, function (item, index) {
              if (item.OrgTicketCode === ticket.OrgTicketCode) {
                tickets[index].StatusLO = 'Не выполнен'
              }
            })

            this.$store.dispatch('pack/setTicketsCollection', tickets)
              .then(() => {
                this.$q.notify({
                  color: 'green-5',
                  textColor: 'white',
                  icon: 'fas fa-check-circle',
                  message: 'Статус НЕ ВЫПОЛНЕНО передано в ЛО.',
                  position: 'top'
                })
              })
          }

          if (result.error !== undefined) {
            this.$q.notify({
              color: 'red-5',
              textColor: 'white',
              icon: 'fas fa-check-circle',
              message: 'Ошибка при передаче в ЛО статуса НЕ ВЫПОЛНЕНО. Ошибка: ' + result.error.Description,
              position: 'top'
            })
          }
        })
    },

    ticketCompleted (ticket) {
      this.$store.dispatch('pack/ticketCompleted', ticket)
        .then((result) => {
          if (result.success !== undefined) {
            const tickets = this.$store.getters['pack/ticketsCollection']

            _.find(tickets, function (item, index) {
              if (item.OrgTicketCode === ticket.OrgTicketCode) {
                tickets[index].StatusLO = 'Выполнен'
              }
            })

            this.$store.dispatch('pack/setTicketsCollection', tickets)
              .then(() => {
                this.$q.notify({
                  color: 'green-5',
                  textColor: 'white',
                  icon: 'fas fa-check-circle',
                  message: 'Статус ВЫПОЛНЕНО передано в ЛО.',
                  position: 'top'
                })
              })
          }

          if (result.error !== undefined) {
            this.$q.notify({
              color: 'red-5',
              textColor: 'white',
              icon: 'fas fa-check-circle',
              message: 'Ошибка при передаче в ЛО статуса ВЫПОЛНЕНО. Ошибка: ' + result.error.Description,
              position: 'top'
            })
          }
        })
    },

    addTicket () {
      this.dialogAddTicket = true
    },

    deleteTicket (ticket) {
      this.isDeletedTicket = true
      this.deleteTicketNumber = ticket
    },

    runDeletedTicket () {
      this.deleteTicketNumber.StatusInPack = 'Deleted'

      this.$store.dispatch('pack/addRowTicket', this.deleteTicketNumber)
        .then(() => {
          this.$q.notify({
            color: 'green-5',
            textColor: 'white',
            icon: 'fas fa-check-circle',
            message: 'Заявка удалена из пачки',
            position: 'top'
          })
          this.isTicketsDeleted = true
          const user = this.$store.getters['auth/user']
          this.collectionTicketsDeleted.push({
            QRCodePacket: this.$store.getters['pack/codePacket'],
            OrgTicketCode: this.deleteTicketNumber.OrgTicketCode,
            OrgTicketBDID: this.deleteTicketNumber.OrgTicketBDID,
            Status: 'Deleted',
            StatusDate: date.formatDate(Date.now(), 'HH:mm:ss'),
            Login: user.login
          })
        })
        .catch((error) => {
          this.$q.notify({
            color: 'red-5',
            textColor: 'white',
            icon: 'fas fa-check-circle',
            message: 'Ошибка при удалении заявки из пачки. Ошибка: ' + error.response.data.message,
            position: 'top'
          })
        })
    },

    searchTicket () {
      this.dialogAddTicketSearch = true
      this.dialogAddTicketNotFound = false
      this.$store.dispatch('pack/searchTicket', this.dialogAddTicketNumber)
        .then((response) => {
          this.dialogInfoTicket = true
          this.foundTicket = response.data
        })
        .catch(() => {
          this.dialogAddTicketNotFound = true
          this.dialogAddTicketSearch = false
        })
    },

    dialogAddTicketClose () {
      this.dialogAddTicket = false
      this.dialogAddTicketSearch = false
      this.dialogAddTicketNumber = ''
      this.dialogInfoTicket = false
      this.dialogAddTicketNotFound = false
    },

    addFoundTicket () {

    },

    cancelFoundTicket () {
      this.dialogInfoTicket = false
      this.dialogAddTicketSearch = false
    },

    cancelOwner (data) {
      this.executorDeleteError = false
      this.executorDelete.executor = data.executor
      this.executorDelete.OrgTicketCode = data.OrgTicketCode
      this.dialogExecutorDelete = true
      this.loadingDelete = true
    },

    cancelOwnerSend () {
      this.$store.dispatch('pack/cancelOwner', this.executorDelete)
        .then((response) => {
          this.executorDeleteError = false
        })
        .catch((error) => {
          this.executorDeleteError = true
          this.executorDeleteErrorText = error
        })
        .finally(() => {
          this.loadingDelete = false
        })
    },

    updateTicket (ticket) {
      this.spinnerUpdateInfoTicket = true
      this.$store.dispatch('pack/updateTicket', ticket)
        .then((response) => {
          this.$store.dispatch('pack/updateTicketByClient', { ticket: ticket, data: response.data })
            .then(() => {
              this.spinnerUpdateInfoTicket = false
            })
        })
    },

    restoreTicket (data) {
      this.isLoadingRestoreTicket = true
      this.$store.dispatch('pack/ticketRestore', data)
        .then(() => {
          this.$store.dispatch('pack/getTicketInfoConfirmed', data.ticket.OrgTicketCode)
            .then(() => {
              this.$store.dispatch('work/addProvenJobs', 'Отмена подтверждения')
                .then((responseAddProvenJobs) => {
                  const tickets = [{ OrgTicketCode: data.ticket.OrgTicketCode }]
                  this.$store.dispatch('work/getWorks', tickets, { root: true })
                    .then((response) => {
                      this.$store.commit('work/provenJobs', false, { root: true })
                      this.isLoadingRestoreTicket = false
                      const collectionTicketsDeleted = this.collectionTicketsDeleted
                      this.collectionTicketsDeleted = _.remove(collectionTicketsDeleted, function (ticket, index) {
                        return index !== data.index
                      })

                      if (this.collectionTicketsDeleted.length === 0) {
                        this.dialogRestoreTicketsDeleted = false
                      }
                    })
                })
            })
        })
    }
  }
}
</script>

<style scoped>
  /*@import url('https://annotorious.github.com/latest/annotorious.css');*/
  @import url('https://welcome.nvbs.ru/annotorious/annotorious.css');

  .pointer {
    cursor: pointer;
  }

  .card-image {
    width: 100%;
  }

  .header-background {
    background-color: #eeeeee;
  }
</style>
