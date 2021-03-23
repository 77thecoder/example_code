import axios from 'axios'
import _ from 'lodash'
import $ from 'jquery'
import { date } from 'quasar'

/**
 * Количество ожидающих проверки
 * @param context
 * @returns {Promise<unknown>}
 */
export const getCountNew = (context) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/packs/countNew')
      .then((response) => {
        context.commit('setCountNew', response.data.count)
        const timestamp = new Date()
        context.commit('timeUpdate', date.formatDate(timestamp, 'HH:mm:ss'))
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Количество на доработке
 * @param context
 * @returns {Promise<unknown>}
 */
export const getCountRework = (context) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/packs/countRework')
      .then((response) => {
        context.commit('setCountRework', response.data.count)
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export const getCountSuccessUser = (context) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/packs/success/count/user')
      .then((response) => {
        context.commit('setCountSuccessUser', response.data.count)
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Количествл пачек в работе у сотрудника
 * @param context
 * @returns {Promise<unknown>}
 */
export const getCountInWorkUser = (context) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/packs/inwork/count')
      .then((response) => {
        context.commit('setCountInWorkUser', response.data.count)
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Пачки в работе у сотрудника
 * @param context
 * @returns {Promise<unknown>}
 */
export const getInWorkUser = (context) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/packs/inwork/user/jobs')
      .then((response) => {
        context.commit('setListInWorkUser', response.data.inWork)
        context.commit('packsUserTotal', response.data.packsUserTotal)
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Количество пачек в даработке у сотрудника
 * @param context
 * @returns {Promise<unknown>}
 */
export const getCountReWorkUser = (context) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/packs/rework/count/user')
      .then((response) => {
        context.commit('setCountReWorkUser', response.data.count)
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Пачки на доработке у сотрудника
 * @param context
 * @returns {Promise<unknown>}
 */
export const getReWorkUser = (context) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/packs/rework/user')
      .then((response) => {
        context.commit('setListReWorkUser', response.data)
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Отправить на оплату
 * @param context
 * @returns {Promise<unknown>}
 */
export const takeToWork = (context) => {
  context.commit('setIsShowBtnTakeToWork', false)
  return new Promise((resolve, reject) => {
    axios.get('/api/packs/takeToWork')
      .then((response) => {
        if (response.data.error === true) {
          context.commit('setAlertTakeToWork', true)
        } else {
          context.commit('setListInWorkUser', response.data)
          context.commit('setCountInWorkUser', Object.keys(response.data).length)
          context.dispatch('getCountNew').then(() => {})
          context.commit('setIsShowBtnTakeToWork', true)
          resolve(response)
        }
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export const setCodePacket = (context, payload) => {
  context.commit('setCodePacket', payload)
}

/**
 * Список документов в пачке
 * @param context
 * @param payload
 * @returns {Promise<unknown>}
 */
export const getDocs = (context, payload) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/documents/qrCode/' + payload)
      .then((response) => {
        if (_.size(response.data.original) === 0) {
          resolve(false)
        } else {
          const res = []
          _.each(response.data.original, function (item) {
            item.isCorrectly = (item.ContractDocStatus === 'Проверен' || item.ContractDocStatus === 'Проверен РГКС')
            res.push(item)
          })
          context.commit('setDocsCollection', res)
          context.commit('setIsSpinnerLoadDocs', false)
          context.commit('setIsLoadDocs', true)
          context.dispatch('getTicketsCollection', payload).then((collection) => {
            resolve(response)
          })
        }
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export const setAlertTakeToWork = (context, payload) => {
  context.commit('setAlertTakeToWork', payload)
}

/**
 * Показываем/скрываем кнопку отправить на оплату
 * @param context
 * @param payload
 */
export const isShowBtnTakeToWork = (context, payload) => {
  context.commit('setIsShowBtnTakeToWork', payload)
}

/**
 * Список тикетов в пачке
 * @param context
 * @param qrcodepacket
 */
export const getTicketsCollection = (context, qrcodepacket) => {
  const docs = context.getters.docsCollection

  const ticket = _.find(docs, (doc) => {
    return doc.OrgTicketCode !== null && doc.OrgTicketDBID !== null
  })

  checkConfirmedTickets(context, qrcodepacket)
    .then((response) => {
      if (response.data.length > 0) {
        getTicketsInfoConfirmed(context, response.data)
          .then((response) => {
            context.dispatch('work/getWorks', response.data, { root: true })
          })
      } else if (response.data.length === 0) {
        getLinkedTickets(context, ticket)
          .then((response) => {
            const qrcodepacket = context.getters.codePacket

            axios.get('/api/pack/tickets/' + qrcodepacket)
              .then((tickets) => {
                const collection = []
                _.each(response.data, function (item) {
                  const find = _.find(tickets.data.Deleted, function (ticket) {
                    return item.OrgTicketCode === ticket.OrgTicketCode
                  })
                  if (!find) {
                    collection.push(item)
                  }
                })
                context.dispatch('setTicketsCollection', collection)
                // context.commit('rgks/tickets', collection, { root: true })
                return collection
              })
              .catch(() => {
                // reject(error)
              })
          })
      }
    })
    .finally(() => {
      getPassportData(context, ticket.OrgTicketBDID).then(() => {})
      context.commit('setIsSpinnerLoadTickets', false)
      context.commit('setIsLoadTickets', true)
    })
}

/**
 * Проверяем наличие подвержденных диспетчером тикетов
 * @param context
 * @param qrcodepacket
 * @returns {Promise<unknown>}
 */
const checkConfirmedTickets = (context, qrcodepacket) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/pack/ticket/get/' + qrcodepacket)
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Получаем информацию по подтвержденным заявкам
 * @param context
 * @param tickets
 */
export const getTicketsInfoConfirmed = (context, tickets) => {
  return new Promise((resolve, reject) => {
    const requests = []

    _.each(tickets, function (ticket, index) {
      requests[index] = axios.post('/api/pack/ticket/info', {
        // TODO idorg нужно будет заменить на переменную идентификатора заказчика, когда в модуле будут другие заказчики
        idorg: 2,
        orgticketcode: (ticket.OrgTicketCode === undefined) ? ticket : ticket.OrgTicketCode
      })
    }, requests)

    axios.all(requests)
      .then((responses) => {
        const ticketsCollection = {}

        _.each(responses, function (ticket) {
          ticket.data.OrgTicketTypeText = ticket.data.CstWorkName
          ticketsCollection[ticket.data.OrgTicketCode] = ticket.data
        }, ticketsCollection)

        context.dispatch('setTicketsCollection', ticketsCollection)
        resolve(responses)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Получаем информацию по подтвержденным заявкам
 * @param context
 * @param tickets
 */
export const getTicketInfoConfirmed = (context, ticket) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/pack/ticket/info', {
      // TODO idorg нужно будет заменить на переменную идентификатора заказчика, когда в модуле будут другие заказчики
      idorg: 2,
      orgticketcode: ticket
    })
      .then((response) => {
        const ticketsCollection = context.getters.ticketsCollection
        response.data.OrgTicketTypeText = response.data.CstWorkName
        ticketsCollection[response.data.OrgTicketCode] = response.data
        context.dispatch('setTicketsCollection', ticketsCollection)
          .then(() => resolve(response))
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Список связанных заявок
 * @param context
 * @param ticket
 * @returns {Promise<unknown>}
 */
export const getLinkedTickets = (context, ticket) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/ticket/linked', {
      ticket: ticket
    })
      .then((response) => {
        const res = []
        _.each(response.data, function (item) {
          res.push(item)
        })
        // context.commit('setTicketsCollection', res)
        context.dispatch('getWorks', res).then(() => {})
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Список работ на заявке
 * @param context
 * @param tickets
 */
export const getWorks = (context, tickets) => {
  context.dispatch('work/getWorks', tickets, { root: true }).then(() => {})
}

/**
 * Информации о паспортных данных абонента
 * @param context
 * @param payload
 * @returns {Promise<unknown>}
 */
export const getPassportData = (context, payload) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/ticket/property', {
      idorg: 2,
      orgticketbdid: payload,
      orgticketpropname: 'ContractNumberTKD'
    })
      .then((response) => {
        axios.get('/api/document/checkPassport/' + response.data.OrgTicketPropValue)
          .then((response) => {
            context.commit('passportData', response.data.data)
            context.commit('passportStatus', response.data.error)
          })
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export const setIsSpinnerLoadTickets = (context, payload) => {
  context.commit('setIsSpinnerLoadTickets', payload)
}

export const setIsLoadTickets = (context, payload) => {
  context.commit('setIsLoadTickets', payload)
}

export const setTicketsCollection = (context, payload) => {
  context.commit('setTicketsCollection', payload)
}

export const setDocsCollection = (context, payload) => {
  context.commit('setDocsCollection', payload)
}

export const setIsViewImage = (context, payload) => {
  context.commit('setIsViewImage', payload)
}

export const setDocumentGuid = (context, payload) => {
  context.commit('setDocumentGuid', payload)
}

export const viewPicture = (context, data) => {
  context.commit('setIsViewImage', true)
  context.commit('setIsLoaderImage', true)
  context.commit('document/info', data, { root: true })
  context.dispatch('document/getComments', data.ContractDocGUID, { root: true })
    .then(() => {
      context.dispatch('document/getPictureSrc', data.ContractDocPath, { root: true }).then(() => {})
    })
}

/**
 * Получение картинки. Добавление/удаление комментариев
 * @param context
 * @param payload
 */
export const viewImage = (context, payload) => {
  context.commit('setIsViewImage', true)
  context.commit('setIsLoaderImage', true)
  context.commit('setDocumentGuid', payload.ContractDocGUID)

  axios.post('/api/image', {
    url: payload.ContractDocPath
  })
    .then((response) => {
      $('div#anno').html('<img src="data:image/png;base64,' + response.data + '" id="img_anno" data-original="' + payload.ContractDocPath + '" width="100%" class="annotatable"/>')

      setTimeout(function () {
        window.anno.reset()
        window.anno.makeAnnotatable(document.getElementById('img_anno'))

        window.anno.setProperties({
          outline: '#fff',
          outline_width: 0,
          stroke_width: 0,
          hi_outline_width: 0,
          hi_stroke_width: 0,
          hi_stroke: '#fff',
          fill: 'rgba(251, 188, 4, 0.2)',
          hi_fill: 'rgba(251, 188, 4, 0.3)'
        })

        getAnnotationCollection(context, payload, response.data).then(() => {})

        context.commit('setIsLoaderImage', false)
      }, 2000)

      window.anno.addHandler('onAnnotationCreated', function (annotation) {
        const collection = context.state.annotationCollection

        collection.push({
          text: annotation.text,
          geometry: {
            x: annotation.shapes[0].geometry.x,
            y: annotation.shapes[0].geometry.y,
            width: annotation.shapes[0].geometry.width,
            height: annotation.shapes[0].geometry.height
          }
        })

        context.commit('setAnnotationCollection', collection)
      })

      window.anno.addHandler('onAnnotationRemoved', function (annotation) {
        const collection = context.state.annotationCollection

        const c = _.filter(collection, (item) => {
          return item.geometry.x !== annotation.shapes[0].geometry.x && item.geometry.y !== annotation.shapes[0].geometry.y
        })

        context.commit('setAnnotationCollection', c)
      })
    })
}

/**
 * Схраняем комментарии в состояниях
 * @param context
 * @param payload
 */
export const setAnnotationCollection = (context, payload) => {
  const docs = context.getters.docsCollection
  const guid = context.getters.documentGuid

  _.find(docs, (item) => {
    if (item.ContractDocGUID === guid) {
      item.annotations = payload

      if (payload.length > 0) {
        item.isCorrectly = false
        item.ContractDocStatus = 'Доработка'
      }
    }
  })

  context.commit('setAnnotationCollection', payload)
  context.commit('setDocsCollection', docs)
}

/**
 * На доработку документ (картинку)
 * @param context
 * @param payload
 * @returns {boolean}
 */
export const rework = (context, payload) => {
  if (context.state.annotationCollection.length === 0) {
    context.commit('setIsDialogReWork', true)
    return false
  }

  const annotationCollection = context.getters.annotationCollection
  setAnnotationCollection(context, annotationCollection)

  clearImage(context, payload)
  context.commit('setIsPackCorrectly', false)
  context.commit('setIsViewImage', false)
  context.commit('setImageSrc', '')
  context.commit('setDocumentGuid', '')
  context.commit('setAnnotationCollection', [])
}

/**
 * Установить статус документу
 * @param context
 * @param documentGuid
 * @param tn
 * @returns {Promise<unknown>}
 */
export const changeStatusDocument = (context, documentGuid, tn) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/document/status', {
      contractdocguid: documentGuid,
      contractdocchangertn: tn,
      oper: 'revision'
    })
      .then((response) => {
        getDocs(context, context.state.codePacket).then(() => {})
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Сохраняем комментарий к документу
 * @param context
 * @param annotationCollection
 * @param documentGuid
 * @param tn
 */
export const addComments = (context, annotationCollection, documentGuid, tn) => {
  return new Promise((resolve, reject) => {
    let count = 0

    _.some(annotationCollection, function (item) {
      if (item.geometry === undefined) return false

      axios.post('/api/document/comment', {
        oper: 'add',
        contractdocguid: documentGuid,
        contractdocnoteauthortn: tn,
        contractdocnotepointx: item.geometry.x,
        contractdocnotepointy: item.geometry.y,
        contractdocnotewidth: item.geometry.width,
        contractdocnoteheight: item.geometry.height,
        contractdocnotetext: item.text
      })
        .then((response) => {
          count++
          if (count === annotationCollection.length) {
            resolve(response)
          }
        })
        .catch((error) => {
          reject(error)
        })
    })
  })
}

/**
 * Сохраняем комментарии к документу в ORM
 * @param context
 * @param annotationCollection
 */
export const saveAnnotationOrm = (context, annotationCollection) => {
  _.some(annotationCollection, function (item) {
    if (item.geometry === undefined) return false

    axios.post('/api/document/comment', {
      documentId: context.getters.documentGuid,
      authorTN: context.rootState.auth.user.tn,
      authorLogin: context.rootState.auth.user.uid,
      pointX: item.geometry.x,
      pointY: item.geometry.y,
      width: item.geometry.width,
      height: item.geometry.height,
      text: item.text
    })
  })
}

/**
 * Меняем статус документу
 * @param guid
 * @param tn
 * @param oper
 * @returns {Promise<unknown>}
 */
export const changeDocumentStatus = (guid, tn, oper) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/document/status', {
      contractdocguid: guid,
      contractdocchangertn: tn,
      oper: oper
    })
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export const correctly = (context, payload) => {
  if (context.state.annotationCollection.length > 0) {
    context.commit('setIsDialogCorrectly', true)
    return true
  }

  const documentGuid = context.state.documentGuid

  clearImage(context, payload)
  context.commit('setIsViewImage', false)
  context.commit('setImageSrc', '')
  context.commit('setDocumentGuid', '')

  const docs = context.getters.docsCollection

  _.find(docs, (item) => {
    if (item.ContractDocGUID === documentGuid) {
      item.isCorrectly = true
      item.ContractDocStatus = 'Проверен'
      item.annotations = []
    }
  })

  context.commit('setDocsCollection', docs)
}

export const correctlyByVersion = (context) => {
  return new Promise((resolve, reject) => {
    const docs = context.getters.docsCollectionByVersion
    const doc = context.rootState.document.info
    docs[doc.ContractDocName].last.ContractDocStatus = 'Проверен'
    docs[doc.ContractDocName].last.isCorrectly = true
    context.commit('docsCollectionByVersion', docs)
    const tn = context.rootState.auth.user.tn

    changeDocumentStatus(doc.ContractDocGUID, tn, 'checked')
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Документ на доработку
 * @param context
 * @returns {Promise<unknown>}
 */
export const reworkByVersion = (context) => {
  return new Promise((resolve, reject) => {
    const docs = context.getters.docsCollectionByVersion
    const doc = context.rootState.document.info
    const comments = context.rootState.document.comments
    docs[doc.ContractDocName].last.ContractDocStatus = 'Доработка'
    docs[doc.ContractDocName].last.isCorrectly = false
    docs[doc.ContractDocName].last.comments = comments
    context.commit('docsCollectionByVersion', docs)
    const tn = context.rootState.auth.user.tn

    changeDocumentStatus(doc.ContractDocGUID, tn, 'revision')
      .then(() => {
        context.dispatch('commentFiltering', { comments: doc.comments, contractDocGUID: doc.ContractDocGUID })
          .then((comments) => {
            addComments(context, comments, doc.ContractDocGUID, tn)
              .then((response) => {
                resolve(response)
              })
              .catch((error) => {
                reject(error)
              })
          })
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Создаем новую версию доработки документа при изменении комментариев документа диспетчером
 * @param context
 * @param doc
 */
export const createNewVersionRework = (context, doc) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/contract/addDoc', {
      QRCodePacket: doc.QRCodePacket,
      ContractDocName: doc.ContractDocName,
      ContractDocType: doc.ContractDocType,
      ContractDocFileName: doc.ContractDocFileName,
      ContractDocFileType: doc.ContractDocFileType,
      ContractDocPath: doc.ContractDocPath,
      ContractDocPathType: doc.ContractDocPathType,
      ContractDocAngle: doc.ContractDocAngle,
      OrgTicketCode: doc.OrgTicketCode,
      OrgTicketBDID: doc.OrgTicketBDID,
      ContractDocSenderTN: context.rootState.auth.user.tn
    })
      .then((response) => {
        createDocStatusAppoint(context, doc)
          .then(() => {
            resolve(response)
          })
      })
      .catch((error) => {
        reject(error.response)
      })
  })
}

/**
 * Ищем в пачке документ со статусом новый, устанавливаем ему статус ДОРАБОТКА, назначаем на него диспетчера
 * @param context
 * @param doc
 */
export const createDocStatusAppoint = (context, doc) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/documents/qrCode/' + doc.QRCodePacket)
      .then((response) => {
        const find = _.find(response.data.original, (item) => {
          return item.ContractDocName === doc.ContractDocName && item.ContractDocStatus === 'Новая версия'
        })

        if (find) {
          const userTN = context.rootState.auth.user.tn

          setAppointed(context, find, userTN)
            .then(() => {
              axios.post('/api/document/status', {
                oper: 'revision',
                contractdocchangertn: userTN,
                contractdocguid: find.ContractDocGUID
              })
                .then((response) => {
                  setCommentByDoc(context, find, userTN)
                    .then(() => {
                      resolve(response)
                    })
                })
                .catch((error) => {
                  reject(error)
                })
            })
        }
      })
  })
}

/**
 * Сохраняем измененные комментарии на заново созданный документ
 * @param context
 * @param doc
 */
const setCommentByDoc = (context, doc) => {
  return new Promise((resolve) => {
    const comments = context.rootState.document.comments
    const requests = []

    _.each(comments, function (comment, index) {
      requests[index] = axios.post('/api/document/comment', {
        oper: 'add',
        contractdocguid: doc.ContractDocGUID,
        contractdocnotetext: comment.text,
        contractdocnotepointx: comment.geometry.x,
        contractdocnotepointy: comment.geometry.y,
        contractdocnoteheight: comment.geometry.height,
        contractdocnotewidth: comment.geometry.width,
        contractdocnoteauthortn: context.rootState.auth.user.tn
      })
    }, { requests: requests, doc: doc })

    axios.all(requests)
      .then((response) => {
        resolve(response)
      })
  })
}

/**
 * Назначаем диспетчера на документ
 * @param context
 * @param doc
 * @param userTN
 * @returns {Promise<unknown>}
 */
const setAppointed = (context, doc, userTN) => {
  return new Promise((resolve) => {
    axios.post('/api/document/doAppoint', {
      oper: 'AddAppoint',
      ContractDocGUID: doc.ContractDocGUID,
      ContractDocAppointerTN: userTN,
      ContractDocAppointedTN: userTN
    })
      .then((response) => {
        resolve(response)
      })
  })
}

/**
 * Фильтруем список комментариев, отделяем новые от старых
 * @param context
 * @param data
 * @returns {Promise<unknown>}
 */
export const commentFiltering = (context, data) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/document/comment/' + data.contractDocGUID)
      .then((response) => {
        if (_.size(response.data) === 0) resolve(data.comments)
        const commentsInitial = response.data
        const filtered = []
        _.each(data.comments, (c) => {
          const find = _.find(commentsInitial, (ci) => {
            return /* c.text === ci.ContractDocNoteText && */c.geometry.x === ci.ContractDocNotePointX && c.geometry.y === ci.ContractDocNotePointY
          })

          if (!find) {
            filtered.push(c)
          }
        })
        resolve(filtered)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Получить список комментариев к документу
 * @param context
 * @param payload
 * @returns {Promise<unknown>}
 */
export const getAnnotationCollection = (context, payload) => {
  const guid = context.state.documentGuid
  const docs = context.getters.docsCollection

  return new Promise((resolve, reject) => {
    const comments = _.filter(docs, (item) => {
      if (item.annotations !== undefined) {
        return item.annotations
      }
    })

    if (comments.length > 0) {
      const doc = _.filter(docs, (item) => {
        return item.ContractDocGUID === guid
      })

      _.each(doc[0].annotations, function (item) {
        const annotations = context.getters.annotationCollection

        annotations.push({
          text: item.text,
          geometry: item.geometry
        })

        context.commit('setAnnotationCollection', annotations)

        var an = {
          src: doc[0].ContractDocPath,
          text: item.text,
          shapes: [{
            type: 'rect',
            geometry: item.geometry
          }]
        }

        window.anno.addAnnotation(an)
      })
    } else {
      axios.get('/api/document/comment/' + guid)
        .then((response) => {
          context.commit('setAnnotationCollection', response.data)
          if (response.data.length > 0) {
            _.each(response.data, function (item) {
              var an = {
                src: payload.ContractDocPath,
                text: item.ContractDocNoteText,
                shapes: [{
                  type: 'rect',
                  geometry: {
                    x: item.ContractDocNotePointX,
                    y: item.ContractDocNotePointY,
                    width: item.ContractDocNoteWidth,
                    height: item.ContractDocNoteHeight
                  }
                }]
              }

              window.anno.addAnnotation(an)
            })
          }
          resolve(response)
        })
        .catch((error) => {
          reject(error)
        })
    }
  })
}

export const getDocumentStatus = (context, payload) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/document/orm/getLastStatus', {
      document_guid: payload.guid,
      pack: payload.pack
    })
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export const setPackReWork = (context) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/pack/orm', {
      code_pack: context.state.codePacket,
      user_tn: context.rootState.auth.user.tn,
      user_login: context.rootState.auth.user.uid,
      status: 'Доработка'
    })
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export const setImagePath = (context, payload) => {
  context.commit('setImagePath', payload)
}

export const setIsDialogReWork = (context, payload) => {
  context.commit('setIsDialogReWork', payload)
}

export const setIsDialogCorrectly = (context, payload) => {
  context.commit('setIsDialogCorrectly', payload)
}

export const setImageSrc = (context, payload) => {
  context.commit('setImageSrc', payload)
}

export const clearImage = () => {
  $('img#img_anno').remove()
  window.anno.reset()
}

export const passportData = (context, payload) => {
  context.commit('passportData', payload)
}

export const passportStatus = (context, payload) => {
  context.commit('passportStatus', payload)
}

/**
 * Добавить/удалить работу в ЛО
 * @param context
 * @param data
 * @returns {Promise<unknown>}
 */
export const putWork = (context, data) => {
  const tn = context.rootState.auth.user.tn

  return new Promise((resolve, reject) => {
    axios.post('/api/lo/putWork', {
      TicketID: data.ticket,
      WorknameID: data.workID,
      Action: data.action,
      UserBinder: tn
    })
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Сохраняем статусы документов в пачке и комментарии
 * @param context
 * @param data
 */
export const sendPackOld = (context, data) => {
  return new Promise((resolve, reject) => {
    const docs = context.getters.docsCollection

    _.each(docs, (doc) => {
      const tn = context.rootState.auth.user.tn

      if (data.action === 'correctly') {
        sendDocsInJQ(context)
          .then(() => {
            changeDocumentStatus(doc.ContractDocGUID, tn, 'checked')
              .then((response) => {
                resolve(response)
              })
              .catch((error) => {
                reject(error)
              })
          })
          .catch((error) => {
            reject(error)
          })
      } else if (data.action === 'rework') {
        if (data.notProvided.length > 0) {
          setNotProvided(context, data.notProvided, docs[0].OrgTicketCode, docs[0].OrgTicketBDID)
            .then((response) => {
              let status

              if (doc.isCorrectly) {
                status = 'checked'
              } else {
                status = 'revision'
              }

              changeDocumentStatus(doc.ContractDocGUID, tn, status)
                .then(() => {
                  addComments(context, doc.annotations, doc.ContractDocGUID, tn)
                    .then((response) => {
                      resolve(response)
                    })
                    .catch((error) => {
                      reject(error)
                    })
                  resolve(response)
                })
                .catch((error) => {
                  reject(error)
                })
            })
            .catch((error) => {
              reject(error)
            })
        }
      }
    })
  })
}

export const sendPack = (context, data) => {
  return new Promise((resolve, reject) => {
    const docs = context.getters.docsCollectionByVersion

    if (data.action === 'rework') {
      if (data.notProvided) {
        const find = _.find(docs, (doc) => {
          return doc.last.OrgTicketCode
        })
        setNotProvided(context, data.notProvided, find)
          .then(() => resolve(true))
      } else {
        deletePackFromStateInWorkUser(context)
        decreaseInWorkUser(context)
        incrementReworkPack(context)
        resolve(true)
      }
    }

    if (data.action === 'correctly') {
      sendDocsInJQ(context)
      packConfirmAdd(context)
        .then((response) => {
          deletePackFromStateInWorkUser(context)
          decreaseInWorkUser(context)
          incrementSuccessPack(context)
          resolve(response)
        })
        .catch((error) => reject(error))
    }
  })
}

/**
 * Удаляем пачки из state в работе диспетчера
 * @param context
 */
export const deletePackFromStateInWorkUser = (context) => {
  const codePacket = context.getters.codePacket
  const packs = context.getters.listInWorkUser

  const collection = packs.filter((pack) => {
    return pack.QRCodePacket !== codePacket
  })

  context.commit('setListInWorkUser', collection)
}

/**
 * Количество успешных увеличиваем на 1
 * @param context
 */
export const incrementSuccessPack = (context) => {
  const successCount = context.getters.countSuccessUser
  context.commit('setCountSuccessUser', successCount + 1)
}

/**
 * Уменьшаем количество в работе на 1
 * @param context
 */
export const decreaseInWorkUser = (context) => {
  const countInWorkUser = context.getters.countInWorkUser
  context.commit('setCountInWorkUser', countInWorkUser - 1)
}

/**
 * Количество на доработке увеличиваем на 1
 * @param context
 */
export const incrementReworkPack = (context) => {
  const reworkCount = context.getters.countReWorkUser
  context.commit('setCountReWorkUser', reworkCount + 1)
}

export const packConfirmAdd = (context) => {
  return new Promise((resolve, reject) => {
    const codePacket = context.getters.codePacket
    const timestamp = new Date()
    const dateAction = date.formatDate(timestamp, 'YYYY-MM-DD HH:mm:ss')

    axios.post('/api/pack/confirm', {
      qrCodePacket: codePacket,
      statusText: 'Подтвержден',
      statusDate: dateAction,
      verifierLogin: context.rootState.auth.user.login,
      verifierTN: context.rootState.auth.user.tn
    })
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error.response)
      })
  })
}

/**
 * Добавить документы, получить их GUID, установить статус "не предоставлен"
 * @param context
 * @param data
 * @param ticket
 * @returns {Promise<unknown>}
 */
const setNotProvided = (context, data, ticket) => {
  return new Promise((resolve, reject) => {
    let countAddDocs = 0
    _.each(data, (item) => {
      axios.post('/api/contract/addDoc', {
        QRCodePacket: context.getters.codePacket,
        ContractDocName: item.label,
        ContractDocType: item.value,
        ContractDocFileName: '',
        ContractDocFileType: '',
        ContractDocPath: '',
        ContractDocPathType: '',
        ContractDocAngle: 0,
        OrgTicketCode: ticket.last.OrgTicketCode,
        OrgTicketBDID: ticket.last.OrgTicketBDID,
        ContractDocSenderTN: ticket.last.ContractDocSenderTN,
        ContractDocAppointedTN: context.rootState.auth.user.th
      })
        .then(() => {
          countAddDocs++
          if (countAddDocs === data.length) {
            axios.get('/api/documents/qrCode/' + context.getters.codePacket)
              .then((response) => {
                const guids = []

                _.each(response.data.original, (item) => {
                  _.each(data, (val) => {
                    if (val.label === item.ContractDocName && item.ContractDocStatus === 'Новый') {
                      guids.push(item.ContractDocGUID)
                    }
                  })
                })

                let countChangeStatus = 0

                _.each(guids, (guid) => {
                  axios.post('/api/document/status', {
                    oper: 'notprovided',
                    contractdocchangertn: context.rootState.auth.user.tn,
                    contractdocguid: guid
                  })
                    .then((response) => {
                      countChangeStatus++
                      if (countChangeStatus === guids.length) {
                        resolve(response)
                      }
                    })
                    .catch((error) => {
                      reject(error)
                    })
                })
              })
          }
        })
    })
  })
}

/**
 * Сохранение записи в очереди на загрузку файлов
 * @param context
 */
export const sendDocsInJQ = (context) => {
  const docs = context.getters.docsCollectionByVersion
  const codePacket = context.getters.codePacket
  const requests = []

  _.each(docs, (doc, index) => {
    requests[index] = axios.post('/api/jq/add', {
      OrgTicketCode: doc.last.OrgTicketCode,
      OrgTicketBDID: doc.last.OrgTicketBDID,
      JQFrom: 'ECP',
      JQAction: 'uploadFileHD',
      JQTo: 'VK',
      JQType: 'HD',
      QRCodePacket: codePacket,
      ContractDocGUID: doc.last.ContractDocGUID,
      ContractDocType: doc.last.ContractDocType,
      ContractDocSenderTN: doc.last.ContractDocSenderTN,
      JQText: doc.last.ContractDocPath
    })
  })

  return new Promise((resolve, reject) => {
    axios.all(requests)
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Передача события в ЛО не выполнено
 * @param context
 * @param ticket
 * @returns {Promise<unknown>}
 */
export const notCompleted = (context, ticket) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/lo/notCompleted', {
      TicketID: ticket.OrgTicketCode,
      UserBind: ticket.TN,
      UserBinder: context.rootState.auth.user.tn
    })
      .then((response) => {
        const result = JSON.parse(response.data)
        resolve(result)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Статус Выполнено в ЛО
 * @param context
 * @param ticket
 * @returns {Promise<unknown>}
 */
export const ticketCompleted = (context, ticket) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/lo/ticketCompleted', {
      TicketID: ticket.OrgTicketCode,
      PersonnelID: ticket.TN,
      UserBinder: context.rootState.auth.user.tn,
      SystemID: '001'
    })
      .then((response) => {
        const result = JSON.parse(response.data)
        resolve(result)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Добавить запись
 * @param context
 * @param ticket
 * @returns {Promise<unknown>}
 */
export const addRowTicket = (context, ticket) => {
  const qrcodepacket = context.getters.codePacket

  return new Promise((resolve, reject) => {
    axios.post('/api/pack/ticket', {
      qrcodepacket: qrcodepacket,
      orgticketcode: ticket.OrgTicketCode,
      orgticketbdid: ticket.OrgTicketBDID,
      status: ticket.StatusInPack,
      login: context.rootState.auth.user.login
    })
      .then((response) => {
        const tickets = context.getters.ticketsCollection

        const filtered = _.filter(tickets, function (item) {
          return item.OrgTicketCode !== ticket.OrgTicketCode
        })

        context.dispatch('setTicketsCollection', filtered)
          .then(() => {
            resolve(response)
          })
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Поиск заявки
 * @param context
 * @param ticket
 * @returns {Promise<unknown>}
 */
export const searchTicket = (context, ticket) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/ticket/search/' + ticket + '/2')
      .then((response) => {
        if (!response.data.OrgTicketCode) {
          reject(response)
        }
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Статус пачки
 * @param context
 * @param qrcodepacket
 * @returns {Promise<unknown>}
 */
export const getStatusPack = (context, qrcodepacket) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/pack/status/' + qrcodepacket)
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

/**
 * Сохраняем подтвержденные тикеты
 * @param context
 */
export const confirmTickets = (context) => {
  const userLogin = context.rootState.auth.user.login
  const userTN = context.rootState.auth.user.tn
  const tickets = context.rootState.pack.ticketsCollection
  const requests = []
  const qrcodepacket = context.rootState.pack.codePacket

  const data = {
    userLogin: userLogin,
    userTN: userTN,
    requests: requests,
    qrcodepacket: qrcodepacket
  }

  return new Promise((resolve, reject) => {
    _.each(tickets, function (ticket, index) {
      if (ticket.OrgStatusCodeText === 'Отказ' || ticket.OrgStatusCodeText === 'Мусор') {
        return
      }
      requests[index] = axios.post('/api/pack/ticket/add', {
        OrgTicketCode: ticket.OrgTicketCode,
        OrgTicketBDID: ticket.OrgTicketBDID,
        QRCodePacket: data.qrcodepacket,
        UserLogin: data.userLogin,
        UserTN: data.userTN
      })
    }, { data: data })
    // TODO не получилось вернуть ответы всех запросов, интерфейс зависает
    resolve(requests)
    // console.log({ requests })
    // Promise.all(requests)
    //   .then((responses) => {
    //     console.log('response 0', responses[0])
    //     console.log('response 1', responses[1])
    //     resolve()
    //   })
    //   .catch((error) => {
    //     console.log('error', error)
    //     reject(error)
    //   })
  })
}

/**
 * Удаление исполнителя на заявке в ЛО
 * @param context
 * @param data
 * @returns {Promise<unknown>}
 */
export const cancelOwner = (context, data) => {
  const timestamp = new Date()
  const dateAction = date.formatDate(timestamp, 'YYYY-MM-DD')

  return new Promise((resolve, reject) => {
    axios.post('/api/lo/cancelOwner', {
      TicketID: data.OrgTicketCode,
      PersonnelID: data.executor.TN,
      UserBinder: context.rootState.auth.user.tn,
      Data: dateAction,
      // TODO идентификатор заказчика нужно получать из пачки
      SystemID: '001',
      source: 'ecp'
    })
      .then((response) => {
        const result = JSON.parse(response.data)
        const responseLO = JSON.parse(result.original)

        if (responseLO.success !== undefined) {
          deleteExecutorFromState(context, data)
            .then((response) => {
              resolve(response)
            })
        } else {
          reject(responseLO.error.ID)
        }
      })
      .catch((error) => {
        reject(error.response)
      })
  })
}

/**
 * Удаляем исполнителя из списка заявок на клиенте
 * @param context
 * @param data
 * @returns {Promise<unknown>}
 */
export const deleteExecutorFromState = (context, data) => {
  return new Promise((resolve, reject) => {
    const tickets = context.getters.ticketsCollection
    const finded = _.find(tickets, (item) => {
      return item.OrgTicketCode === data.OrgTicketCode
    })

    let executors = {}

    if (finded) {
      executors = _.filter(finded.Executors, (item) => {
        return item.TN !== data.executor.TN
      })
    }

    _.each(tickets, (ticket, index) => {
      if (ticket.OrgTicketCode === data.OrgTicketCode) {
        tickets[index].Executors = executors
      }
    })

    context.dispatch('setTicketsCollection', tickets)
    resolve(executors)
  })
}

/**
 * Получаем список на доработке
 * @param context
 * @returns {unknown[]}
 */
export const getReworkUserFromState = (context) => {
  const packs = context.state.packsUserTotal

  const listRework = _.filter(packs, (pack) => {
    return pack.QRCodeStatus === 'Доработка'
  })

  context.commit('setListReWorkUser', listRework)
  context.commit('setCountReWorkUser', _.size(listRework))
}

/**
 * Считаем кол-во успешно проверенных
 * @param context
 */
export const getCountSuccessUserFromState = (context) => {
  const packs = context.state.packsUserTotal

  const listSuccess = _.filter(packs, (pack) => {
    return pack.QRCodeStatus === 'Проверен'
    // включим позже, Ксения пока хочет видеть как РГКС подтверждают
    // return pack.QRCodeStatus === 'Проверен' || pack.QRCodeStatus === 'Проверен РГКС'
  })

  context.commit('setCountSuccessUser', _.size(listSuccess))
}

/**
 * Обновление информации о заявке в АМУР2 из системы заказчика ВК
 * @param context
 * @param ticket
 */
export const updateTicket = (context, ticket) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/ticket/updateInfoTicket', {
      orgticketcode: ticket.OrgTicketCode
    })
      .then((response) => {
        resolve(response)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export const updateTicketByClient = (context, data) => {
  return new Promise((resolve, reject) => {
    data.ticket.OrgStatusCodeText = data.data.OrgStatusCodeText
    data.ticket.OrgTicketTypeText = data.data.OrgTicketTypeText
    const ticketsCollection = context.rootState.pack.ticketsCollection

    _.find(ticketsCollection, (ticket, index) => {
      if (ticket.OrgTicketCode === data.ticket.OrgTicketCode) {
        ticketsCollection[index] = data.ticket
      }
    })

    context.dispatch('setTicketsCollection', ticketsCollection)

    resolve(true)
  })
}

/**
 * Получаем послежний статус пачки из списка подтвержденных диспетчером
 * @param context
 * @param qrCodePacket
 * @returns {Promise<unknown>}
 */
export const getStatusConfirmPack = (context, qrCodePacket) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/pack/confirm/get', {
      qrCodePacket: qrCodePacket
    })
      .then((response) => resolve(response))
      .catch((error) => reject(error))
  })
}

/**
 * Список заявок удленных из пачки
 * @param context
 * @param qrCodePacket
 * @returns {Promise<unknown>}
 */
export const getTicketsDeleted = (context, qrCodePacket) => {
  return new Promise((resolve, reject) => {
    axios.get('/api/pack/' + qrCodePacket + '/ticket/deleted')
      .then((response) => {
        const deleted = _.filter(response.data, (v) => {
          return v.Status === 'Deleted'
        })
        resolve(deleted)
      })
      .catch((error) => reject(error))
  })
}

/**
 * Восстановление заявки
 * @param context
 * @param data
 * @returns {Promise<unknown>}
 */
export const ticketRestore = (context, data) => {
  const statusDate = date.formatDate(Date.now(), 'YYYY-MM-DD HH:mm:ss')

  return new Promise((resolve, reject) => {
    axios.post('/api/pack/ticket/restore', {
      qrcodepacket: data.ticket.QRCodePacket,
      orgticketcode: data.ticket.OrgTicketCode,
      orgticketbdid: data.ticket.OrgTicketBDID,
      status: 'Restored',
      statusdate: statusDate,
      login: context.rootState.auth.user.login
    })
      .then((response) => resolve(response))
      .catch((error) => reject(error))
  })
}

/**
 * Информация о заявке из ЛО
 * @param context
 * @param ticket
 * @returns {Promise<unknown>}
 */
export const getTicketData = (context, ticket) => {
  return new Promise((resolve, reject) => {
    axios.post('/api/lo/getTicketData', {
      TicketID: ticket
    })
      .then((response) => resolve(response))
      .catch((error) => reject(error))
  })
}
